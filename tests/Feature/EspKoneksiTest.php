<?php

namespace Tests\Feature;

use App\Enums\StatusMesin;
use App\Models\EspMapping;
use App\Models\Mesin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EspKoneksiTest extends TestCase
{
    use RefreshDatabase;

    private EspMapping $esp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->esp = EspMapping::create([
            'id_esp' => '9C12B8F23A08',
            'mac_address' => '08:3A:F2:B8:12:9C',
            'kode_mesin' => 1,
            'nama_mesin' => 'AC',
        ]);
    }

    public function test_ping_dengan_chip_id_mencatat_koneksi(): void
    {
        $this->postJson('/api/esp/ping', [], ['X-ESP-ID' => '9C12B8F23A08'])
            ->assertOk()
            ->assertJsonPath('esp.kode_mesin', 1)
            ->assertJsonPath('esp.nama_mesin', 'AC');

        $this->esp->refresh();
        $this->assertNotNull($this->esp->last_seen_at);
        $this->assertTrue($this->esp->isOnline());
    }

    public function test_ping_dengan_mac_address_juga_dikenali(): void
    {
        $this->postJson('/api/esp/ping', [], ['X-ESP-ID' => '08:3A:F2:B8:12:9C'])
            ->assertOk()
            ->assertJsonPath('esp.id_esp', '9C12B8F23A08');
    }

    public function test_polling_kontrol_mesin_ikut_mencatat_koneksi(): void
    {
        $this->getJson('/api/kontrol-mesin?id_esp=9c12b8f23a08')->assertOk();

        $this->assertTrue($this->esp->fresh()->isOnline());
    }

    public function test_esp_tidak_terdaftar_ditolak_saat_ping(): void
    {
        $this->postJson('/api/esp/ping', [], ['X-ESP-ID' => 'AABBCCDDEEFF'])->assertNotFound();

        $this->assertNull($this->esp->fresh()->last_seen_at);
    }

    public function test_state_mengembalikan_nama_status_dan_timer_untuk_polling_esp(): void
    {
        $this->postJson('/api/esp/status', ['status' => 'RUNNING'], ['X-ESP-ID' => '9C12B8F23A08'])
            ->assertOk();

        $this->getJson('/api/esp/state?id_esp=9C12B8F23A08')
            ->assertOk()
            ->assertJsonPath('esp.nama_mesin', 'AC')
            ->assertJsonPath('esp.status', 'RUNNING')
            ->assertJsonPath('esp.kode_mesin', 1);
    }

    public function test_timer_state_diambil_dari_backend_bukan_dihitung_lokal(): void
    {
        $this->freezeSecond();
        $this->postJson('/api/esp/status', ['status' => 'SETTER'], ['X-ESP-ID' => '9C12B8F23A08']);

        $this->travel(7)->seconds();

        $this->getJson('/api/esp/state?id_esp=9C12B8F23A08')
            ->assertOk()
            ->assertJsonPath('esp.timer_sec', 7)
            ->assertJsonPath('esp.durasi_status_sec', 7);
    }

    public function test_state_dari_esp_tidak_terdaftar_ditolak(): void
    {
        $this->getJson('/api/esp/state?id_esp=AABBCCDDEEFF')->assertNotFound();
    }

    public function test_state_mengambil_nama_dari_tabel_mesin(): void
    {
        Mesin::create(['uid' => '1001', 'nama' => 'Monitor Baru', 'nomor' => 1]);

        $this->getJson('/api/esp/state?id_esp=9C12B8F23A08')
            ->assertOk()
            ->assertJsonPath('esp.nama_mesin', 'Monitor Baru')
            ->assertJsonPath('esp.kode_mesin', 1);
    }

    public function test_state_pakai_nama_esp_bila_tabel_mesin_kosong(): void
    {
        $this->getJson('/api/esp/state?id_esp=9C12B8F23A08')
            ->assertOk()
            ->assertJsonPath('esp.nama_mesin', 'AC');
    }

    public function test_esp_dianggap_terputus_setelah_batas_waktu(): void
    {
        $this->postJson('/api/esp/ping', [], ['X-ESP-ID' => '9C12B8F23A08']);

        $this->travel(EspMapping::BATAS_ONLINE_DETIK + 1)->seconds();

        $this->assertFalse($this->esp->fresh()->isOnline());
    }

    public function test_dashboard_menampilkan_mesin_dari_esp_yang_terhubung(): void
    {
        EspMapping::create(['id_esp' => 'AABBCCDDEEFF', 'kode_mesin' => 2, 'nama_mesin' => 'Monitor']);
        EspMapping::create(['id_esp' => '112233445566', 'kode_mesin' => 3, 'nama_mesin' => 'Mesin Cuci']);

        Livewire::test('mesin-monitor')->assertSee('Tidak ada');

        $this->postJson('/api/esp/ping', [], ['X-ESP-ID' => '9C12B8F23A08']);
        $this->postJson('/api/esp/ping', [], ['X-ESP-ID' => '112233445566']);

        Livewire::test('mesin-monitor')
            ->assertSee('Mesin 1 (AC), Mesin 3 (Mesin Cuci)')
            ->assertDontSee('Mesin 2 (Monitor)');
    }

    public function test_timer_berjalan_saat_terhubung_berhenti_saat_terputus_dan_reset_saat_konek_lagi(): void
    {
        $this->freezeSecond();
        $header = ['X-ESP-ID' => '9C12B8F23A08'];

        $this->postJson('/api/esp/ping', [], $header);
        $this->travel(5)->seconds();
        $this->postJson('/api/esp/ping', [], $header);
        $this->assertSame(5, $this->esp->fresh()->durasiKoneksi());

        // Terputus: timer berhenti di durasi terakhir (5 detik)
        $this->travel(EspMapping::BATAS_ONLINE_DETIK + 30)->seconds();
        $this->assertFalse($this->esp->fresh()->isOnline());
        $this->assertSame(5, $this->esp->fresh()->durasiKoneksi());

        // Konek lagi: sesi baru, timer mulai dari nol
        $this->postJson('/api/esp/ping', [], $header);
        $this->assertSame(0, $this->esp->fresh()->durasiKoneksi());
    }

    public function test_esp_mengirim_status_running_mould_setter_off(): void
    {
        $header = ['X-ESP-ID' => '9C12B8F23A08'];

        foreach (['RUNNING', 'mould', 'SETTER', 'off'] as $status) {
            $this->postJson('/api/esp/status', ['status' => $status], $header)
                ->assertOk()
                ->assertJsonPath('esp.status', strtoupper($status));
        }

        $this->assertSame(StatusMesin::Off, $this->esp->fresh()->status);
    }

    public function test_status_tidak_valid_ditolak(): void
    {
        $this->postJson('/api/esp/status', ['status' => 'ON'], ['X-ESP-ID' => '9C12B8F23A08'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertNull($this->esp->fresh()->status);
    }

    public function test_status_dari_esp_tidak_terdaftar_ditolak(): void
    {
        $this->postJson('/api/esp/status', ['status' => 'RUNNING'], ['X-ESP-ID' => 'AABBCCDDEEFF'])
            ->assertNotFound();
    }

    public function test_timer_status_reset_saat_status_berubah_tapi_tidak_saat_klik_ulang(): void
    {
        $this->freezeSecond();
        $header = ['X-ESP-ID' => '9C12B8F23A08'];

        $this->postJson('/api/esp/status', ['status' => 'RUNNING'], $header);
        $this->travel(5)->seconds();
        $this->postJson('/api/esp/status', ['status' => 'RUNNING'], $header);
        $this->assertSame(5, $this->esp->fresh()->durasiStatus());

        $this->postJson('/api/esp/status', ['status' => 'MOULD'], $header);
        $this->assertSame(0, $this->esp->fresh()->durasiStatus());
    }

    public function test_timer_sec_tersimpan_di_tabel_esp_dan_reset_saat_ganti_status(): void
    {
        $this->freezeSecond();
        $header = ['X-ESP-ID' => '9C12B8F23A08'];

        $this->postJson('/api/esp/status', ['status' => 'RUNNING'], $header);
        $this->travel(7)->seconds();
        $this->postJson('/api/esp/ping', [], $header)->assertJsonPath('esp.timer_sec', 7);
        $this->assertDatabaseHas('esp_mapping', ['id' => $this->esp->id, 'timer_sec' => 7]);

        $this->postJson('/api/esp/status', ['status' => 'OFF'], $header)->assertJsonPath('esp.timer_sec', 0);
        $this->assertSame(0, $this->esp->fresh()->timer_sec);
    }

    public function test_dashboard_menampilkan_status_mesin(): void
    {
        $this->postJson('/api/esp/status', ['status' => 'SETTER'], ['X-ESP-ID' => '9C12B8F23A08']);

        Livewire::test('mesin-monitor')
            ->assertSee('Mesin 1 (AC - SETTER)')
            ->assertSee('Durasi SETTER');
    }
}

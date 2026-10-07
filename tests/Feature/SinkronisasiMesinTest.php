<?php

namespace Tests\Feature;

use App\Enums\StatusMesin;
use App\Models\EspMapping;
use App\Models\Mesin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SinkronisasiMesinTest extends TestCase
{
    use RefreshDatabase;

    private EspMapping $esp;

    private Mesin $mesin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mesin = Mesin::create([
            'uid' => '1001',
            'nama' => 'AC',
            'nomor' => 1,
        ]);

        $this->esp = EspMapping::create([
            'id_esp' => 'ESP32_01',
            'mac_address' => '08:3A:F2:B8:12:9C',
            'kode_mesin' => 1,
            'nama_mesin' => 'AC',
        ]);
    }

    public function test_status_esp_mengaktifkan_mesin_yang_dipakai_aplikasi(): void
    {
        $this->postJson('/api/esp/status', ['status' => 'RUNNING'], ['X-ESP-ID' => 'ESP32_01'])
            ->assertOk();

        $this->mesin->refresh();
        $this->assertTrue($this->mesin->status);
        $this->assertNotNull($this->mesin->started_at);
    }

    public function test_status_off_dari_esp_mematikan_mesin(): void
    {
        $this->postJson('/api/esp/status', ['status' => 'RUNNING'], ['X-ESP-ID' => 'ESP32_01']);
        $this->postJson('/api/esp/status', ['status' => 'OFF'], ['X-ESP-ID' => 'ESP32_01'])
            ->assertOk();

        $this->mesin->refresh();
        $this->assertFalse($this->mesin->status);
        $this->assertNull($this->mesin->started_at);
    }

    public function test_menyalakan_dari_aplikasi_mengubah_status_esp_dan_dashboard(): void
    {
        $this->postJson('/api/kontrol-mesin/1/on')->assertOk();

        $this->assertSame(StatusMesin::Running, $this->esp->fresh()->status);

        $this->getJson('/api/esp/state?id_esp=ESP32_01')
            ->assertOk()
            ->assertJsonPath('esp.status', 'RUNNING');

        Livewire::test('mesin-monitor')->assertSee('RUNNING');
    }

    public function test_mematikan_dari_aplikasi_mengubah_status_esp(): void
    {
        $this->postJson('/api/esp/status', ['status' => 'SETTER'], ['X-ESP-ID' => 'ESP32_01'])
            ->assertOk();

        $this->postJson('/api/kontrol-mesin/1/off')->assertOk();

        $this->assertSame(StatusMesin::Off, $this->esp->fresh()->status);
        $this->assertFalse($this->mesin->fresh()->status);
    }

    public function test_status_spesial_esp_tidak_tersentuh_selama_mesin_masih_aktif(): void
    {
        $this->postJson('/api/esp/status', ['status' => 'MOULD'], ['X-ESP-ID' => 'ESP32_01'])
            ->assertOk();

        // Mesin sudah ON sehingga aplikasi tidak boleh menimpa status MOULD
        $this->assertTrue($this->mesin->fresh()->status);
        $this->assertSame(StatusMesin::Mould, $this->esp->fresh()->status);

        // Status berikutnya tetap dari ESP32
        $this->postJson('/api/esp/status', ['status' => 'SETTER'], ['X-ESP-ID' => 'ESP32_01']);

        $this->assertSame(StatusMesin::Setter, $this->esp->fresh()->status);
        $this->assertTrue($this->mesin->fresh()->status);
    }

    public function test_nama_mesin_disinkronkan_ke_dua_tabel(): void
    {
        $this->mesin->update(['nama' => 'AC Pendingin']);
        $this->assertSame('AC Pendingin', $this->esp->fresh()->nama_mesin);

        $this->esp->fresh()->update(['nama_mesin' => 'AC Ruang Tamu']);
        $this->assertSame('AC Ruang Tamu', $this->mesin->fresh()->nama);
    }

    public function test_durasi_off_tidak_berjalan_dan_durasi_terakhir_tersimpan(): void
    {
        $this->freezeSecond();
        $header = ['X-ESP-ID' => 'ESP32_01'];

        $this->postJson('/api/esp/status', ['status' => 'RUNNING'], $header);
        $this->travel(5)->seconds();
        $this->postJson('/api/esp/status', ['status' => 'OFF'], $header)->assertJsonPath('esp.timer_sec', 0);

        $this->travel(30)->seconds();

        $esp = $this->esp->fresh();
        $this->assertSame(StatusMesin::Off, $esp->status);
        $this->assertSame(0, $esp->durasiStatus());
        $this->assertSame(0, $esp->timer_sec);

        // Durasi terakhir sebelum dimatikan tetap tercatat di tabel mesin
        $this->assertSame(5, $this->mesin->fresh()->timer_sec);
    }
}

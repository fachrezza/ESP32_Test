<?php

namespace Tests\Feature;

use App\Enums\StatusMesin;
use App\Models\EspMapping;
use App\Models\Mesin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardKontrolTest extends TestCase
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

    public function test_tombol_nyalakan_terlihat_di_kartu(): void
    {
        Livewire::test('mesin-monitor')
            ->assertOk()
            ->assertSee('Nyalakan')
            ->assertDontSee('Matikan');
    }

    public function test_tombol_dashboard_menyalakan_mesin_dan_sinkron_ke_esp(): void
    {
        Livewire::test('mesin-monitor')
            ->call('toggleMesin', 1)
            ->assertSet('pesan', 'AC dinyalakan')
            ->assertSet('galat', null)
            ->assertSee('Matikan');

        $this->assertTrue($this->mesin->fresh()->status);
        $this->assertSame(StatusMesin::Running, $this->esp->fresh()->status);

        $this->getJson('/api/esp/state?id_esp=ESP32_01')
            ->assertOk()
            ->assertJsonPath('esp.status', 'RUNNING');
    }

    public function test_tombol_dashboard_mematikan_mesin_dan_menyimpan_durasi(): void
    {
        $this->freezeSecond();

        Livewire::test('mesin-monitor')->call('toggleMesin', 1);
        $this->travel(5)->seconds();

        Livewire::test('mesin-monitor')
            ->call('toggleMesin', 1)
            ->assertSet('pesan', 'AC dimatikan setelah 5 detik')
            ->assertSet('galat', null)
            ->assertSee('Nyalakan');

        $mesin = $this->mesin->fresh();
        $this->assertFalse($mesin->status);
        $this->assertSame(5, $mesin->timer_sec);
        $this->assertSame(StatusMesin::Off, $this->esp->fresh()->status);
    }

    public function test_tombol_ditolak_bila_mesin_lain_masih_menyala(): void
    {
        Mesin::create(['uid' => '1002', 'nama' => 'Monitor', 'nomor' => 2]);
        EspMapping::create([
            'id_esp' => 'ESP32_02',
            'mac_address' => '08:3A:F2:B8:12:9D',
            'kode_mesin' => 2,
            'nama_mesin' => 'Monitor',
        ]);

        Livewire::test('mesin-monitor')->call('toggleMesin', 1);

        Livewire::test('mesin-monitor')
            ->call('toggleMesin', 2)
            ->assertSet('pesan', null)
            ->assertSet('galat', 'Tidak bisa menyalakan Monitor. AC masih menyala, matikan dulu.');

        $this->assertFalse(Mesin::where('nomor', 2)->value('status'));
    }

    public function test_tombol_untuk_mesin_tidak_dikenal_menampilkan_galat(): void
    {
        Livewire::test('mesin-monitor')
            ->call('toggleMesin', 99)
            ->assertSet('pesan', null)
            ->assertSet('galat', 'Mesin 99 tidak ditemukan');
    }
}

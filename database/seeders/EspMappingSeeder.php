<?php

namespace Database\Seeders;

use App\Models\EspMapping;
use Illuminate\Database\Seeder;

class EspMappingSeeder extends Seeder
{
    public function run(): void
    {
        $daftarEsp = [
            ['id_esp' => 'ESP32_01', 'mac_address' => '08:3A:F2:B8:12:9C', 'kode_mesin' => 1, 'nama_mesin' => 'AC'],
            ['id_esp' => 'ESP32_02', 'mac_address' => '01:F4:E3:D2:C1:A4', 'kode_mesin' => 2, 'nama_mesin' => 'Monitor'],      // dummy
            ['id_esp' => 'ESP32_03', 'mac_address' => '02:A2:F1:E0:D9:B8', 'kode_mesin' => 3, 'nama_mesin' => 'Mesin Cuci'],   // dummy
        ];

        foreach ($daftarEsp as $esp) {
            EspMapping::updateOrCreate(
                ['id_esp' => $esp['id_esp']],
                [
                    'mac_address' => $esp['mac_address'],
                    'kode_mesin'  => $esp['kode_mesin'],
                    'nama_mesin'  => $esp['nama_mesin'],
                ]
            );
        }
    }
}

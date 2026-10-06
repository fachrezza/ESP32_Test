<?php

namespace Database\Seeders;

use App\Models\Mesin;
use Illuminate\Database\Seeder;

class MesinSeeder extends Seeder
{
    public function run(): void
    {
        $daftarMesin = [
            ['nomor' => 1, 'uid' => '1001', 'nama' => 'AC'],
            ['nomor' => 2, 'uid' => '1002', 'nama' => 'Monitor'],
            ['nomor' => 3, 'uid' => '1003', 'nama' => 'Mesin Cuci'],
        ];

        foreach ($daftarMesin as $m) {
            Mesin::updateOrCreate(
                ['nomor' => $m['nomor']],
                [
                    'uid'        => $m['uid'],
                    'nama'       => $m['nama'],
                    'status'     => false,
                    'started_at' => null,
                    'timer_sec'  => 0,
                ]
            );
        }
    }
}
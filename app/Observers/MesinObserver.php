<?php

namespace App\Observers;

use App\Enums\StatusMesin;
use App\Models\EspMapping;
use App\Models\Mesin;

class MesinObserver
{
    /**
     * Status/nama mesin berubah (mis. dari aplikasi Flutter lewat
     * POST /api/kontrol-mesin/{nomor}/on|off) -> teruskan ke esp_mapping
     * supaya dashboard dan LCD ESP32 ikut berubah.
     *
     * Aturan ekuivalensi:
     *   mesin OFF  <=> esp_mapping.status = OFF
     *   mesin ON   <=> esp_mapping.status = RUNNING / MOULD / SETTER
     *   mesin ON saat esp masih OFF -> esp di-set RUNNING
     *   mesin ON saat esp sudah MOULD/SETTER -> status spesial esp TIDAK dirusak
     */
    public function updated(Mesin $mesin): void
    {
        if ($mesin->wasChanged('nama')) {
            EspMapping::where('kode_mesin', $mesin->nomor)
                ->where('nama_mesin', '!=', $mesin->nama)
                ->update(['nama_mesin' => $mesin->nama]);
        }

        if (! $mesin->wasChanged('status')) {
            return;
        }

        foreach (EspMapping::where('kode_mesin', $mesin->nomor)->get() as $esp) {
            $statusBaru = $mesin->status
                ? ($esp->isActive() ? $esp->status : StatusMesin::Running)
                : StatusMesin::Off;

            // Jangan tulis bila nilainya sudah sama: inilah yang memutus rantai observer
            if ($esp->status === $statusBaru) {
                continue;
            }

            $esp->update([
                'status' => $statusBaru,
                'status_since' => now(),
            ]);
        }
    }
}

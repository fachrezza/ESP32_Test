<?php

namespace App\Observers;

use App\Models\EspMapping;
use App\Models\Mesin;

class EspMappingObserver
{
    /**
     * Status/nama ESP berubah (mis. tombol fisik di ESP32 lewat POST /api/esp/status)
     * -> teruskan ke tabel mesin supaya aplikasi Flutter (GET /api/kontrol-mesin)
     * ikut melihat status yang sama dengan dashboard.
     *
     * Hanya status berubah yang memicu sinkronisasi, sehingga update per-request
     * dari middleware CatatKoneksiEsp (ip_address / last_seen_at) tidak ikut menulis.
     */
    public function updated(EspMapping $esp): void
    {
        if ($esp->wasChanged('nama_mesin')) {
            Mesin::where('nomor', $esp->kode_mesin)
                ->where('nama', '!=', $esp->nama_mesin)
                ->update(['nama' => $esp->nama_mesin]);
        }

        if (! $esp->wasChanged('status')) {
            return;
        }

        $mesin = Mesin::where('nomor', $esp->kode_mesin)->first();

        if (! $mesin) {
            return;
        }

        $nyala = $esp->isActive();

        // Sudah sama -> jangan tulis apa pun, agar rantai observer berhenti
        if ($mesin->status === $nyala) {
            return;
        }

        $mesin->update([
            'status' => $nyala,
            'started_at' => $nyala ? ($mesin->started_at ?? now()) : null,
            // Saat dimatikan, simpan durasi terakhir sebelum status berganti
            'timer_sec' => $nyala ? 0 : (int) $esp->getOriginal('timer_sec'),
        ]);
    }
}

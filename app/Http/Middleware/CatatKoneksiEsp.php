<?php

namespace App\Http\Middleware;

use App\Models\EspMapping;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CatatKoneksiEsp
{
    /**
     * Baca ID ESP dari header "X-ESP-ID" (atau query ?id_esp=) lalu catat waktu terakhir terlihat.
     * Request tanpa ID / ID belum terdaftar tetap diteruskan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $esp = EspMapping::cariDariIdentitas($request->header('X-ESP-ID') ?? $request->query('id_esp'));

        if ($esp) {
            // Sebelumnya terputus -> ini awal sesi koneksi baru, timer mulai dari nol.
            // Status terakhir tetap dipakai, tapi durasinya dihitung ulang dari sekarang.
            if (! $esp->isOnline()) {
                $esp->connected_at = now();
                $esp->status_since = $esp->status ? now() : null;
            }

            $esp->update([
                'ip_address'   => $request->ip(),
                'last_seen_at' => now(),
            ]);

            $request->attributes->set('esp', $esp);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mesin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class KontrolMesinController extends Controller
{
    /**
     * GET /api/kontrol-mesin
     * Status semua mesin (dipakai Arduino untuk polling).
     */
    public function index(): JsonResponse
    {
        return response()->json($this->payload());
    }

    /**
     * POST /api/kontrol-mesin/{nomor}/on
     */
    public function on(int $nomor): JsonResponse
    {
        return DB::transaction(function () use ($nomor) {
            // Kunci semua baris agar dua request ON bersamaan tidak lolos dua-duanya
            $semua = Mesin::lockForUpdate()->get()->keyBy('nomor');

            $mesin = $semua->get($nomor);
            if (! $mesin) {
                return $this->error("Mesin $nomor tidak ditemukan", 404);
            }

            if ($mesin->status) {
                return $this->error("Mesin $nomor sudah menyala", 409, $mesin);
            }

            $aktif = $semua->first(fn ($m) => $m->status);
            if ($aktif) {
                return $this->error(
                    "Tidak bisa menyalakan {$mesin->nama}. {$aktif->nama} masih menyala, matikan dulu.",
                    409,
                    $aktif
                );
            }

            $mesin->update([
                'status'     => true,
                'started_at' => now(),
                'timer_sec'  => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$mesin->nama} dinyalakan",
                'mesin'   => $this->infoMesin($mesin),
                'data'    => $this->payload(),
            ]);
        });
    }

    /**
     * POST /api/kontrol-mesin/{nomor}/off
     */
    public function off(int $nomor): JsonResponse
    {
        return DB::transaction(function () use ($nomor) {
            $mesin = Mesin::where('nomor', $nomor)->lockForUpdate()->first();

            if (! $mesin) {
                return $this->error("Mesin $nomor tidak ditemukan", 404);
            }

            if (! $mesin->status) {
                return $this->error("Mesin $nomor sudah mati", 409, $mesin);
            }

            $durasi = $mesin->currentTimer();

            $mesin->update([
                'status'     => false,
                'started_at' => null,
                'timer_sec'  => $durasi,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$mesin->nama} dimatikan setelah $durasi detik",
                'mesin'   => $this->infoMesin($mesin),
                'data'    => $this->payload(),
            ]);
        });
    }

    /**
     * POST /api/kontrol-mesin/{nomor}/toggle
     * Praktis untuk tombol fisik di Arduino: satu tombol, ON/OFF bergantian.
     */
    public function toggle(int $nomor): JsonResponse
    {
        $mesin = Mesin::where('nomor', $nomor)->first();

        if (! $mesin) {
            return $this->error("Mesin $nomor tidak ditemukan", 404);
        }

        return $mesin->status ? $this->off($nomor) : $this->on($nomor);
    }

    /**
     * Bentuk JSON sesuai schema:
     * { active_machine, mesin_1: {status, timer_sec}, mesin_2: {...}, mesin_3: {...} }
     */
    private function payload(): array
    {
        $semua = Mesin::orderBy('nomor')->get();
        $aktif = $semua->first(fn ($m) => $m->status);

        $data = [
            'active_machine' => $aktif?->nomor,                          // tetap ada agar kompatibel
            'active_info'    => $aktif ? $this->infoMesin($aktif) : null, // detail mesin aktif
        ];

        foreach ($semua as $m) {
            $data["mesin_{$m->nomor}"] = [
                'uid'       => $m->uid,
                'nama'      => $m->nama,
                'status'    => $m->status,
                'timer_sec' => $m->currentTimer(),
            ];
        }

        return $data;
    }

    private function infoMesin(Mesin $m): array
    {
        return [
            'nomor'     => $m->nomor,
            'uid'       => (string) $m->uid,   // string agar nol di depan (mis. "0042") tidak hilang
            'nama'      => $m->nama,
            'status'    => $m->status,
            'timer_sec' => $m->currentTimer(),
        ];
    }

    private function error(string $message, int $code, ?Mesin $mesin = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'mesin'   => $mesin ? $this->infoMesin($mesin) : null,
            'data'    => $this->payload(),
        ], $code);
    }
}

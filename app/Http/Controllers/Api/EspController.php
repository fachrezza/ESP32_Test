<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusMesin;
use App\Http\Controllers\Controller;
use App\Models\EspMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EspController extends Controller
{
    /**
     * POST /api/esp/ping   (header X-ESP-ID: 9C12B8F23A08)
     * Dipanggil ESP32 saat baru terhubung / sebagai heartbeat. Mengembalikan mapping mesinnya.
     */
    public function ping(Request $request): JsonResponse
    {
        $esp = $this->espDariRequest($request);

        if (! $esp) {
            return $this->belumTerdaftar();
        }

        return response()->json([
            'success' => true,
            'message' => "Terhubung sebagai {$esp->namaUntukTampilan()}",
            'esp' => $this->infoEsp($esp),
        ]);
    }

    /**
     * GET /api/esp/state   (header X-ESP-ID, atau ?id_esp=ESP32_01)
     * Dipakai ESP32 untuk polling: mengembalikan nama_mesin, status, dan timer_sec
     * sebagai satu-satunya sumber kebenaran untuk tampilan di perangkat.
     */
    public function state(Request $request): JsonResponse
    {
        $esp = $this->espDariRequest($request);

        if (! $esp) {
            return $this->belumTerdaftar();
        }

        return response()->json([
            'success' => true,
            'esp' => $this->infoEsp($esp),
        ]);
    }

    /**
     * POST /api/esp/status   (header X-ESP-ID, body: {"status": "RUNNING" | "MOULD" | "SETTER" | "OFF"})
     * Dipanggil saat tombol status di ESP32 diklik.
     */
    public function status(Request $request): JsonResponse
    {
        $esp = $this->espDariRequest($request);

        if (! $esp) {
            return $this->belumTerdaftar();
        }

        $request->merge(['status' => strtoupper((string) $request->input('status'))]);
        $validated = $request->validate([
            'status' => ['required', Rule::enum(StatusMesin::class)],
        ]);

        $statusBaru = StatusMesin::from($validated['status']);

        // Klik tombol yang sama berulang kali tidak me-reset timer
        if ($esp->status !== $statusBaru) {
            $esp->update([
                'status' => $statusBaru,
                'status_since' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "{$esp->namaUntukTampilan()} sekarang {$statusBaru->value}",
            'esp' => $this->infoEsp($esp),
        ]);
    }

    private function espDariRequest(Request $request): ?EspMapping
    {
        return $request->attributes->get('esp');
    }

    private function infoEsp(EspMapping $esp): array
    {
        return [
            'id_esp' => $esp->id_esp,
            'mac_address' => $esp->mac_address,
            'kode_mesin' => $esp->kode_mesin,
            'nama_mesin' => $esp->namaUntukTampilan(),
            'status' => $esp->status?->value,
            'durasi_status_sec' => $esp->durasiStatus(),
            'timer_sec' => $esp->timer_sec,
        ];
    }

    private function belumTerdaftar(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'ESP belum terdaftar atau header X-ESP-ID tidak dikirim',
        ], 404);
    }
}

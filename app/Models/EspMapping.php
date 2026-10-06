<?php

namespace App\Models;

use App\Enums\StatusMesin;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class EspMapping extends Model
{
    /** ESP dianggap terputus jika tidak mengakses API selama sekian detik. */
    public const BATAS_ONLINE_DETIK = 10;

    protected $table = 'esp_mapping';

    protected $fillable = ['id_esp', 'mac_address', 'kode_mesin', 'nama_mesin', 'status', 'status_since', 'timer_sec', 'ip_address', 'connected_at', 'last_seen_at'];

    protected $casts = [
        'kode_mesin'   => 'integer',
        'status'       => StatusMesin::class,
        'status_since' => 'datetime',
        'timer_sec'    => 'integer',
        'connected_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Simpan durasi status saat ini setiap kali data ESP disimpan (ping, polling, ganti status)
        static::saving(function (self $esp) {
            $esp->timer_sec = $esp->status && $esp->status_since
                ? (int) $esp->status_since->diffInSeconds($esp->last_seen_at ?? now(), true)
                : 0;
        });
    }

    /**
     * Cari ESP dari identitas yang dikirim perangkat.
     * Menerima chip ID HEX ("9C12B8F23A08") maupun MAC ("08:3A:F2:B8:12:9C" / "083AF2B8129C").
     */
    public static function cariDariIdentitas(?string $identitas): ?self
    {
        $identitas = trim((string) $identitas);

        if ($identitas === '') {
            return null;
        }

        // Cocokkan id_esp apa adanya (mis. "ESP32_01")
        $esp = static::where('id_esp', $identitas)->first();
        if ($esp) {
            return $esp;
        }

        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $identitas));

        if (strlen($hex) !== 12) {
            return null;
        }

        // ESP.getEfuseMac() menghasilkan MAC dengan urutan byte terbalik, jadi cek kedua urutan
        $bytes       = str_split($hex, 2);
        $macNormal   = implode(':', $bytes);
        $macTerbalik = implode(':', array_reverse($bytes));

        return static::where('id_esp', $hex)
            ->orWhereIn('mac_address', [$macNormal, $macTerbalik])
            ->first();
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->diffInSeconds(now(), true) <= self::BATAS_ONLINE_DETIK;
    }

    /**
     * Durasi koneksi dalam detik: berjalan selama terhubung, berhenti di durasi terakhir saat terputus.
     */
    public function durasiKoneksi(): int
    {
        return $this->durasiSejak($this->connected_at);
    }

    /**
     * Durasi mesin berada di status saat ini (RUNNING / MOULD / SETTER / OFF), dengan aturan berhenti yang sama.
     */
    public function durasiStatus(): int
    {
        return $this->status ? $this->durasiSejak($this->status_since) : 0;
    }

    private function durasiSejak(?CarbonInterface $mulai): int
    {
        if (! $mulai || ! $this->last_seen_at) {
            return 0;
        }

        $akhir = $this->isOnline() ? now() : $this->last_seen_at;

        return (int) $mulai->diffInSeconds($akhir, true);
    }
}

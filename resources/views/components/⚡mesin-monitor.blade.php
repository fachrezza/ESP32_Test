<?php

use App\Http\Controllers\Api\KontrolMesinController;
use App\Models\EspMapping;
use App\Models\Mesin;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /** Pesan sukses / galat dari tombol nyalakan-matikan. */
    public ?string $pesan = null;
    public ?string $galat = null;

    #[Computed]
    public function esps()
    {
        return EspMapping::orderBy('kode_mesin')->get();
    }

    /** Lookup mesin per nomor, dipakai tombol nyalakan/matikan di kartu. */
    #[Computed]
    public function mesinMap()
    {
        return Mesin::orderBy('nomor')->get()->keyBy('nomor');
    }

    #[Computed]
    public function mesinAktif()
    {
        // Mesin dianggap aktif selama ESP32-nya terhubung ke backend
        return $this->esps
            ->filter(fn ($esp) => $esp->isOnline())
            ->unique('kode_mesin')
            ->values();
    }

    /**
     * Tombol Nyalakan/Matikan di kartu.
     * Aturan bisnisnya diambil dari KontrolMesinController (satu-satunya sumber),
     * supaya dashboard, aplikasi Flutter, dan API tidak pernah beda perilaku.
     */
    public function toggleMesin(int $nomor): void
    {
        $response = app(KontrolMesinController::class)->toggle($nomor);
        $data = $response->getData(true);

        if ($response->getStatusCode() === 200) {
            $this->pesan = $data['message'] ?? 'OK';
            $this->galat = null;

            return;
        }

        $this->galat = $data['message'] ?? 'Gagal memproses permintaan.';
        $this->pesan = null;
    }

    public function formatDurasi(int $detik): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($detik, 3600), intdiv($detik % 3600, 60), $detik % 60);
    }
};
?>

<div wire:poll.1s class="monitor">
    <div class="summary">
        <span class="label">Mesin aktif</span>
        <span class="value">
            @if ($this->mesinAktif->isEmpty())
                Tidak ada
            @else
                {{ $this->mesinAktif->map(fn ($esp) => "Mesin {$esp->kode_mesin} ({$esp->namaUntukTampilan()}".($esp->status ? " - {$esp->status->value}" : '').')')->join(', ') }}
            @endif
        </span>
        <span class="updated">Diperbarui {{ now()->format('H:i:s') }}</span>
    </div>

    @if ($pesan)
        <div class="flash ok" role="status">{{ $pesan }}</div>
    @endif
    @if ($galat)
        <div class="flash err" role="alert">{{ $galat }}</div>
    @endif

    <div class="grid">
        @forelse ($this->esps as $esp)
            @php($online = $esp->isOnline())
            <div wire:key="esp-{{ $esp->id }}"
                 class="card {{ $online ? 'on' : 'off' }} {{ $online && $esp->status ? 'status-'.strtolower($esp->status->value) : '' }}">
                <div class="card-head">
                    <div>
                        <h2>Mesin {{ $esp->kode_mesin }}</h2>
                        <span class="subtitle">{{ $esp->namaUntukTampilan() }}</span>
                    </div>
                    <span class="badge">
                        <span class="dot"></span>
                        {{ $online ? 'TERHUBUNG' : 'TERPUTUS' }}
                    </span>
                </div>

                <div class="status-pill">{{ $esp->status?->value ?? 'BELUM ADA STATUS' }}</div>

                <div class="timer">{{ $this->formatDurasi($esp->durasiStatus()) }}</div>
                <div class="timer-label">
                    @if (! $esp->status)
                        Menunggu status dari ESP32
                    @elseif ($esp->isActive())
                        {{ $online ? 'Durasi '.$esp->status->value : 'Durasi '.$esp->status->value.' terakhir' }}
                    @else
                        Mesin mati
                    @endif
                </div>

                <div class="meta">
                    <div>Status sejak: {{ $esp->status_since?->format('d M Y H:i:s') ?? '-' }}</div>
                    <div>Terhubung sejak: {{ $esp->connected_at?->format('d M Y H:i:s') ?? '-' }} ({{ $this->formatDurasi($esp->durasiKoneksi()) }})</div>
                    <div>Terakhir terlihat: {{ $esp->last_seen_at?->diffForHumans() ?? 'belum pernah' }}</div>
                    <div>ID ESP: {{ $esp->id_esp }} &middot; IP {{ $esp->ip_address ?? '-' }}</div>
                </div>

                @php($mesin = $this->mesinMap[$esp->kode_mesin] ?? null)
                @if ($mesin)
                    <button type="button"
                            class="btn {{ $mesin->status ? 'btn-off' : 'btn-on' }}"
                            wire:click="toggleMesin({{ $esp->kode_mesin }})"
                            wire:loading.attr="disabled"
                            wire:target="toggleMesin({{ $esp->kode_mesin }})">
                        {{ $mesin->status ? 'Matikan' : 'Nyalakan' }}
                    </button>
                @else
                    <button type="button" class="btn" disabled>Belum ada data mesin</button>
                @endif
            </div>
        @empty
            <p class="empty">Belum ada ESP32 yang terdaftar.</p>
        @endforelse
    </div>
</div>

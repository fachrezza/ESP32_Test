<?php

use App\Models\EspMapping;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function esps()
    {
        return EspMapping::orderBy('kode_mesin')->get();
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
                {{ $this->mesinAktif->map(fn ($esp) => "Mesin {$esp->kode_mesin} ({$esp->nama_mesin}".($esp->status ? " - {$esp->status->value}" : '').')')->join(', ') }}
            @endif
        </span>
        <span class="updated">Diperbarui {{ now()->format('H:i:s') }}</span>
    </div>

    <div class="grid">
        @forelse ($this->esps as $esp)
            @php($online = $esp->isOnline())
            <div wire:key="esp-{{ $esp->id }}"
                 class="card {{ $online ? 'on' : 'off' }} {{ $online && $esp->status ? 'status-'.strtolower($esp->status->value) : '' }}">
                <div class="card-head">
                    <div>
                        <h2>Mesin {{ $esp->kode_mesin }}</h2>
                        <span class="subtitle">{{ $esp->nama_mesin }}</span>
                    </div>
                    <span class="badge">
                        <span class="dot"></span>
                        {{ $online ? 'TERHUBUNG' : 'TERPUTUS' }}
                    </span>
                </div>

                <div class="status-pill">{{ $esp->status?->value ?? 'BELUM ADA STATUS' }}</div>

                <div class="timer">{{ $this->formatDurasi($esp->durasiStatus()) }}</div>
                <div class="timer-label">
                    @if ($esp->status)
                        {{ $online ? 'Durasi '.$esp->status->value : 'Durasi '.$esp->status->value.' terakhir' }}
                    @else
                        Menunggu status dari ESP32
                    @endif
                </div>

                <div class="meta">
                    <div>Status sejak: {{ $esp->status_since?->format('d M Y H:i:s') ?? '-' }}</div>
                    <div>Terhubung sejak: {{ $esp->connected_at?->format('d M Y H:i:s') ?? '-' }} ({{ $this->formatDurasi($esp->durasiKoneksi()) }})</div>
                    <div>Terakhir terlihat: {{ $esp->last_seen_at?->diffForHumans() ?? 'belum pernah' }}</div>
                    <div>ID ESP: {{ $esp->id_esp }} &middot; IP {{ $esp->ip_address ?? '-' }}</div>
                </div>
            </div>
        @empty
            <p class="empty">Belum ada ESP32 yang terdaftar.</p>
        @endforelse
    </div>
</div>

@php
    $sections = [
        ['key' => 'perlu-pembaruan', 'judul' => 'Perlu Pembaruan', 'items' => $this->perluPembaruan, 'kosong' => 'Tidak ada kegiatan yang tertunda.', 'tone' => 'warning'],
        ['key' => 'mendatang', 'judul' => 'Hari Ini & Mendatang', 'items' => $this->mendatang, 'kosong' => 'Belum ada kegiatan terjadwal.', 'tone' => 'default'],
        ['key' => 'riwayat', 'judul' => 'Riwayat Terakhir', 'items' => $this->riwayat, 'kosong' => 'Belum ada riwayat.', 'tone' => 'muted'],
    ];
    $statusTone = [
        'belum_dimulai' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        'sedang_berlangsung' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200',
        'selesai' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200',
        'dibatalkan' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200',
    ];
@endphp

<x-filament-panels::page>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-8">
        @foreach ($sections as $section)
            <section aria-labelledby="judul-{{ $section['key'] }}" class="flex flex-col gap-3">
                <h2 id="judul-{{ $section['key'] }}" class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                    {{ $section['judul'] }}
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $section['items']->count() }}</span>
                </h2>

                @forelse ($section['items'] as $kegiatan)
                    <article
                        wire:key="kegiatan-{{ $kegiatan->id }}"
                        data-testid="kartu-kegiatan"
                        @class([
                            'flex flex-col gap-3 rounded-xl border bg-white p-4 shadow-xs dark:bg-gray-900',
                            'border-amber-300 dark:border-amber-700' => $section['tone'] === 'warning',
                            'border-gray-200 dark:border-white/10' => $section['tone'] !== 'warning',
                        ])
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-gray-950 dark:text-white">{{ $kegiatan->sekolah->nama }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $kegiatan->tahap->label() }}@if ($kegiatan->nama_sesi !== $kegiatan->tahap->label()) · {{ $kegiatan->nama_sesi }}@endif</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ $statusTone[$kegiatan->status->value] }}">
                                {{ $kegiatan->status->label() }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-1 gap-1 text-sm text-gray-700 sm:grid-cols-2 dark:text-gray-300">
                            <div class="flex gap-2">
                                <dt class="sr-only">Tanggal</dt>
                                <dd>{{ $kegiatan->tanggal->translatedFormat('l, d M Y') }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="sr-only">Waktu</dt>
                                <dd>{{ substr($kegiatan->waktu_mulai, 0, 5) }}–{{ substr($kegiatan->waktu_selesai, 0, 5) }} WIB · {{ $kegiatan->metode->nama }}</dd>
                            </div>
                            @if ($kegiatan->lokasi)
                                <div class="flex gap-2 sm:col-span-2">
                                    <dt class="sr-only">Lokasi</dt>
                                    <dd class="break-words">{{ $kegiatan->lokasi }}</dd>
                                </div>
                            @endif
                            @if ($kegiatan->tautan_pertemuan)
                                <div class="flex gap-2 sm:col-span-2">
                                    <dt class="sr-only">Tautan</dt>
                                    <dd class="min-w-0 truncate">
                                        <a href="{{ $kegiatan->tautan_pertemuan }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline dark:text-primary-400">Buka tautan pertemuan</a>
                                    </dd>
                                </div>
                            @endif
                        </dl>

                        @if ($kegiatan->status->isOpen())
                            <div class="grid grid-cols-2 gap-2 [&_.fi-btn]:min-h-11 [&_.fi-btn]:w-full [&_.fi-btn]:justify-center">
                                {{ ($this->reportAction)(['kegiatan' => $kegiatan->id]) }}
                                {{ ($this->rescheduleAction)(['kegiatan' => $kegiatan->id]) }}
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">{{ $section['kosong'] }}</p>
                @endforelse
            </section>
        @endforeach
    </div>
</x-filament-panels::page>

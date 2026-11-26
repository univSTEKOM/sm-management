@php
    /** @var \App\Domain\Jadwal\Models\Kegiatan $kegiatan */
    $batal = $kegiatan->status === \App\Domain\Shared\Enums\StatusKegiatan::Dibatalkan;
@endphp

<dl class="grid gap-2 text-sm" data-testid="kalender-detail" data-kegiatan-id="{{ $kegiatan->id }}">
    @if ($batal)
        <div class="rounded-lg bg-gray-100 px-3 py-2 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">Kegiatan ini dibatalkan.</div>
    @endif
    <div><dt class="font-medium">Kegiatan</dt><dd>{{ $kegiatan->tahap->label() }} · {{ $kegiatan->nama_sesi }}</dd></div>
    <div><dt class="font-medium">Cabang</dt><dd>{{ $kegiatan->sekolah->cabang?->nama ?? '-' }}</dd></div>
    <div><dt class="font-medium">Tanggal</dt><dd>{{ $kegiatan->tanggal->translatedFormat('l, d F Y') }}, {{ substr((string) $kegiatan->waktu_mulai, 0, 5) }}–{{ substr((string) $kegiatan->waktu_selesai, 0, 5) }}</dd></div>
    <div><dt class="font-medium">Status</dt><dd>{{ $kegiatan->status->label() }}</dd></div>
    <div><dt class="font-medium">Mentor pelaksana</dt><dd>{{ $kegiatan->mentorPelaksana?->name ?? '-' }}</dd></div>
    <div><dt class="font-medium">Metode</dt><dd>{{ $kegiatan->metode?->nama ?? '-' }}</dd></div>
    @if ($bolehLihatDetail)
        @if (filled($kegiatan->lokasi))
            <div><dt class="font-medium">Lokasi</dt><dd>{{ $kegiatan->lokasi }}</dd></div>
        @endif
        @if (filled($kegiatan->tautan_pertemuan))
            <div><dt class="font-medium">Tautan pertemuan</dt><dd class="break-all">{{ $kegiatan->tautan_pertemuan }}</dd></div>
        @endif
    @endif
</dl>

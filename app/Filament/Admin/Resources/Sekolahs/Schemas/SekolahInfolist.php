<?php

namespace App\Filament\Admin\Resources\Sekolahs\Schemas;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Support\Authorization\ScopeResolver;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * Detail Sekolah (PRD 13.3, UI brief section "Detail Sekolah"). Activities of both stages
 * and the contacts are relation managers below these tabs. Report notes and certificate
 * links follow their own abilities (laporan.view_detail, sertifikat.open_link), so a mentor
 * who may see every school (A-01) still sees notes and links only for assigned schools.
 */
class SekolahInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('detail')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Ringkasan')->icon('heroicon-o-building-library')->schema([
                            Section::make('Identitas')->columns(3)->schema([
                                TextEntry::make('nama'),
                                TextEntry::make('jenjang')->badge()->color(fn (Jenjang $state) => $state->color()),
                                TextEntry::make('kabupatenKota.nama')->label('Kabupaten/Kota'),
                                TextEntry::make('cabang.nama')->label('Cabang'),
                                TextEntry::make('tanggal_mitra')->label('Tanggal mitra')->date('d M Y')->placeholder('-'),
                                TextEntry::make('kategori.nama')->label('Kategori pelatihan')->badge()->color('gray')->placeholder('-'),
                                TextEntry::make('alamat')->columnSpanFull()->placeholder('-'),
                            ]),
                            Section::make('Status')->columns(3)->schema([
                                TextEntry::make('status_pelatihan')->label('Status pelatihan')->badge()
                                    ->state(fn (Sekolah $record) => $record->statusPelatihan()->label())
                                    ->color(fn (Sekolah $record) => $record->statusPelatihan()->color()),
                                TextEntry::make('posisi_tahap')->label('Posisi tahap')->badge()
                                    ->formatStateUsing(fn (PosisiTahap $state) => $state->label())
                                    ->color(fn (PosisiTahap $state) => $state->color()),
                                TextEntry::make('progres_tl')->label('Progres Tindak Lanjut')
                                    ->state(fn (Sekolah $record) => $record->progresTl() ?? 'Belum ada sesi'),
                                TextEntry::make('mou')->label('MOU')->badge()
                                    ->state(fn (Sekolah $record) => $record->isMou() ? 'Sudah MOU' : 'Belum MOU')
                                    ->color(fn (Sekolah $record) => $record->isMou() ? 'success' : 'gray'),
                                TextEntry::make('mou_tanggal')->label('Tanggal MOU')->date('d M Y')->placeholder('-')
                                    ->visible(fn (Sekolah $record) => $record->isMou()),
                                TextEntry::make('mou_dokumen_path')->label('Dokumen MOU')
                                    ->formatStateUsing(fn (Sekolah $record) => $record->mou_dokumen_nama_asli ?? 'Unduh dokumen')
                                    ->url(fn (Sekolah $record) => $record->mouDownloadUrl(), shouldOpenInNewTab: true)
                                    ->visible(fn (Sekolah $record) => $record->isMou() && Gate::allows('viewMou', $record)),
                                TextEntry::make('penugasanAktif.mentor.name')->label('Mentor penanggung jawab')->placeholder('Belum ditugaskan'),
                                TextEntry::make('narahubungUtama.nama')->label('Narahubung utama')->placeholder('-')
                                    ->helperText(fn (Sekolah $record) => $record->narahubungUtama?->jabatan),
                                TextEntry::make('narahubungUtama.nomor_wa')->label('WhatsApp')->placeholder('-')
                                    ->url(fn (Sekolah $record) => $record->narahubungUtama?->waUrl(), shouldOpenInNewTab: true),
                            ]),
                            Section::make('Arsip')->columns(2)
                                ->visible(fn (Sekolah $record) => $record->isArchived())
                                ->schema([
                                    TextEntry::make('diarsipkan_at')->label('Diarsipkan pada')->dateTime('d M Y H:i'),
                                    TextEntry::make('alasan_arsip')->label('Alasan'),
                                ]),
                        ]),
                        Tab::make('Penugasan Mentor')->icon('heroicon-o-user-group')->schema([
                            RepeatableEntry::make('penugasan')
                                ->hiddenLabel()
                                ->placeholder('Belum pernah ada penugasan.')
                                ->columns(4)
                                ->schema([
                                    TextEntry::make('mentor.name')->label('Mentor'),
                                    TextEntry::make('mulai')->dateTime('d M Y H:i'),
                                    TextEntry::make('selesai')->dateTime('d M Y H:i')->placeholder('Aktif'),
                                    TextEntry::make('alasan_selesai')->label('Keterangan')->placeholder('-'),
                                ]),
                        ]),
                        Tab::make('Progres & Catatan')->icon('heroicon-o-document-text')
                            ->visible(fn (Sekolah $record) => ScopeResolver::allows(auth()->user(), 'laporan.view_detail', $record))
                            ->schema([
                                RepeatableEntry::make('laporanRevisi')
                                    ->hiddenLabel()
                                    ->placeholder('Belum ada catatan laporan.')
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('kegiatan.nama_sesi')->label('Kegiatan')
                                            ->helperText(fn ($record) => $record->kegiatan->tahap->label()),
                                        TextEntry::make('penulis.name')->label('Ditulis oleh'),
                                        TextEntry::make('created_at')->label('Tanggal')->dateTime('d M Y H:i'),
                                        TextEntry::make('status')->badge()->formatStateUsing(fn (StatusKegiatan $state) => $state->label())->color(fn (StatusKegiatan $state) => $state->color()),
                                        TextEntry::make('catatan')->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Riwayat')->icon('heroicon-o-clock')->schema([
                            Section::make('Riwayat narahubung')->schema([
                                RepeatableEntry::make('riwayatNarahubung')
                                    ->hiddenLabel()
                                    ->placeholder('Belum ada riwayat.')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                                        TextEntry::make('pengubah.name')->label('Diubah oleh')
                                            ->helperText(fn ($record) => $record->usulan_id ? 'Dari usulan mentor' : null),
                                        TextEntry::make('perubahan')->label('Perubahan')
                                            ->state(fn ($record) => self::describeChange($record->data_sebelum, $record->data_sesudah)),
                                    ]),
                            ]),
                            Section::make('Koreksi cabang')
                                ->visible(fn (Sekolah $record) => $record->koreksiCabang->isNotEmpty())
                                ->schema([
                                    RepeatableEntry::make('koreksiCabang')
                                        ->hiddenLabel()
                                        ->columns(4)
                                        ->schema([
                                            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                                            TextEntry::make('cabangLama.nama')->label('Cabang lama'),
                                            TextEntry::make('cabangBaru.nama')->label('Cabang baru'),
                                            TextEntry::make('pengoreksi.name')->label('Oleh'),
                                            TextEntry::make('alasan')->columnSpanFull(),
                                        ]),
                                ]),
                        ]),
                        Tab::make('Sertifikat')->icon('heroicon-o-academic-cap')->schema([
                            Grid::make(2)->schema(collect(Tahap::cases())->map(fn (Tahap $tahap) => Section::make('Sertifikat '.$tahap->label())->schema([
                                TextEntry::make('sertifikat_'.$tahap->value)->hiddenLabel()->badge()
                                    ->state(fn (Sekolah $record) => $record->statusSertifikat($tahap)->label())
                                    ->color(fn (Sekolah $record) => $record->statusSertifikat($tahap)->color()),
                                TextEntry::make('sertifikat_link_'.$tahap->value)->label('Tautan')
                                    ->state('Buka sertifikat')
                                    ->url(fn (Sekolah $record) => $record->sertifikat->firstWhere('tahap', $tahap)?->link, shouldOpenInNewTab: true)
                                    ->visible(fn (Sekolah $record) => $record->sertifikat->firstWhere('tahap', $tahap)?->isTersedia()
                                        && ScopeResolver::allows(auth()->user(), 'sertifikat.open_link', $record)),
                            ]))->all()),
                        ]),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function describeChange(?array $before, ?array $after): string
    {
        $labels = ['nama' => 'Nama', 'jabatan' => 'Jabatan', 'nomor_wa' => 'WhatsApp', 'email' => 'Email', 'is_utama' => 'Utama', 'aktif' => 'Aktif'];
        $after ??= [];

        if ($before === null) {
            return 'Ditambahkan: '.($after['nama'] ?? '-');
        }

        $format = fn ($value) => is_bool($value) ? ($value ? 'ya' : 'tidak') : (filled($value) ? (string) $value : '-');

        return collect($labels)
            ->filter(fn (string $label, string $field) => ($before[$field] ?? null) !== ($after[$field] ?? null))
            ->map(fn (string $label, string $field) => "{$label}: ".$format($before[$field] ?? null).' → '.$format($after[$field] ?? null))
            ->implode('; ') ?: 'Tidak ada perubahan data';
    }
}

<?php

namespace App\Filament\Support;

use App\Domain\Jadwal\Actions\AssignPelaksanaKegiatan;
use App\Domain\Jadwal\Actions\CreateJadwalAwal;
use App\Domain\Jadwal\Actions\CreateSesiTindakLanjut;
use App\Domain\Jadwal\Actions\UpdateKegiatanDetail;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Jadwal\Models\KegiatanJadwalRiwayat;
use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * Filament actions for creating and maintaining schedules. They only collect input and call the
 * domain actions (CreateJadwalAwal, CreateSesiTindakLanjut, AssignPelaksanaKegiatan, UpdateKegiatanDetail);
 * every rule and authorization check lives there. Shared by both panels.
 */
final class JadwalActions
{
    /** @param  Closure(): Sekolah  $sekolah */
    public static function tambahAwal(Closure $sekolah, ?Closure $after = null): Action
    {
        return Action::make('tambahAwal')
            ->label('Jadwalkan Pelatihan Awal')
            ->icon('heroicon-o-calendar-days')
            ->visible(fn () => Gate::allows('createAwal', $sekolah())
                && ! $sekolah()->kegiatan()->where('tahap', Tahap::Awal->value)->where('status', '<>', StatusKegiatan::Dibatalkan->value)->exists())
            ->modalDescription('Pelatihan awal diselenggarakan satu kali, offline di kampus pusat.')
            ->schema([
                Placeholder::make('lokasi_tetap')->label('Metode dan lokasi')
                    ->content(fn () => 'Offline — '.AppSetting::get(AppSetting::LOKASI_KAMPUS_PUSAT, '(lokasi kampus pusat belum diatur)')),
                ...self::waktuFields(),
                TextInput::make('detail_lokasi')->label('Detail ruang (opsional)')->maxLength(500),
                ...self::pelaksanaField($sekolah),
                Textarea::make('keterangan')->label('Keterangan')->rows(2),
            ])
            ->action(function (Action $action, array $data) use ($sekolah, $after) {
                RunsDomainAction::run($action, fn () => app(CreateJadwalAwal::class)->handle(auth()->user(), $sekolah(), self::input($data)), 'Jadwal pelatihan awal dibuat');
                $after?->__invoke();
            });
    }

    /** @param  Closure(): Sekolah  $sekolah */
    public static function tambahSesi(Closure $sekolah, ?Closure $after = null): Action
    {
        return Action::make('tambahSesi')
            ->label('Tambah Sesi Tindak Lanjut')
            ->icon('heroicon-o-plus')
            ->visible(fn () => Gate::allows('createTl', $sekolah()))
            ->modalDescription(fn () => $sekolah()->komposisiTl().'. Informasi saja, tidak ada batas jumlah atau komposisi sesi.')
            ->schema([
                TextInput::make('nama_sesi')->label('Nama sesi (opsional)')->placeholder('Kosongkan untuk "Sesi n"')->maxLength(150),
                ...self::waktuFields(),
                Select::make('metode_id')->label('Metode')->required()->live()
                    ->options(fn () => MetodeKegiatan::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')->all()),
                TextInput::make('lokasi')->label('Lokasi')->maxLength(1000)
                    ->visible(fn (Get $get) => self::metode($get('metode_id'))?->butuh_lokasi === true)
                    ->required(fn (Get $get) => self::metode($get('metode_id'))?->butuh_lokasi === true),
                TextInput::make('tautan_pertemuan')->label('Tautan pertemuan')->url()->maxLength(1000)
                    ->visible(fn (Get $get) => self::metode($get('metode_id'))?->butuh_tautan === true)
                    ->required(fn (Get $get) => self::metode($get('metode_id'))?->butuh_tautan === true),
                ...self::pelaksanaField($sekolah),
                Textarea::make('keterangan')->label('Keterangan')->rows(2),
            ])
            ->action(function (Action $action, array $data) use ($sekolah, $after) {
                RunsDomainAction::run($action, fn () => app(CreateSesiTindakLanjut::class)->handle(auth()->user(), $sekolah(), self::input($data)), 'Sesi ditambahkan');
                $after?->__invoke();
            });
    }

    public static function tunjukPelaksana(): Action
    {
        return Action::make('tunjukPelaksana')
            ->label('Tunjuk Pelaksana')
            ->icon('heroicon-o-user-circle')
            ->visible(fn (Kegiatan $record) => Gate::allows('assignPelaksana', $record))
            ->fillForm(fn (Kegiatan $record) => ['version' => $record->version])
            ->schema(fn (Kegiatan $record) => [
                Select::make('mentor_id')->label('Mentor pelaksana')->required()->searchable()
                    ->options(fn () => self::mentorOptions()->forget($record->mentor_pelaksana_id)->all()),
                Hidden::make('version'),
            ])
            ->action(function (Action $action, Kegiatan $record, array $data) {
                RunsDomainAction::run($action, fn () => app(AssignPelaksanaKegiatan::class)
                    ->handle(auth()->user(), $record, (int) $data['mentor_id'], isset($data['version']) ? (int) $data['version'] : null), 'Pelaksana ditunjuk');
            });
    }

    public static function ubahDetail(): Action
    {
        return Action::make('ubahDetail')
            ->label('Ubah Detail')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (Kegiatan $record) => Gate::allows('updateDetail', $record))
            ->fillForm(fn (Kegiatan $record) => [
                'nama_sesi' => $record->nama_sesi,
                'jenis_kegiatan_id' => $record->jenis_kegiatan_id,
                'keterangan' => $record->keterangan,
                'version' => $record->version,
            ])
            ->schema(fn (Kegiatan $record) => [
                TextInput::make('nama_sesi')->label('Nama sesi')->required()->maxLength(150)
                    ->visible($record->tahap === Tahap::TindakLanjut)
                    ->helperText('Nomor urut tidak berubah.'),
                Select::make('jenis_kegiatan_id')->label('Jenis kegiatan')->required()
                    ->options(fn () => JenisKegiatan::query()->where('aktif', true)->orWhere('id', $record->jenis_kegiatan_id)->orderBy('nama')->pluck('nama', 'id')->all()),
                Textarea::make('keterangan')->label('Keterangan')->rows(3),
                Hidden::make('version'),
            ])
            ->action(function (Action $action, Kegiatan $record, array $data) {
                RunsDomainAction::run($action, function () use ($record, $data) {
                    $version = isset($data['version']) ? (int) $data['version'] : null;
                    unset($data['version']);
                    app(UpdateKegiatanDetail::class)->handle(auth()->user(), $record, $data, $version);
                }, 'Detail diperbarui');
            });
    }

    /** BR-ACT-06: every schedule version, who changed it and why. */
    public static function riwayat(): Action
    {
        return Action::make('riwayatJadwal')
            ->label('Riwayat Jadwal')
            ->icon('heroicon-o-clock')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (Kegiatan $record) => new HtmlString(self::riwayatHtml($record)));
    }

    public static function riwayatHtml(Kegiatan $record): string
    {
        $rows = $record->riwayatJadwal()->get()->map(function (KegiatanJadwalRiwayat $r) {
            $oleh = User::query()->find($r->diubah_oleh)->name ?? '—';

            return '<li><strong>Versi '.e($r->versi).'</strong> · '.e($r->created_at->translatedFormat('d M Y H:i')).' · '.e($oleh)
                .'<br>'.e(self::ringkas($r->sebelum)).' → '.e(self::ringkas($r->sesudah))
                .'<br><em>Alasan: '.e($r->alasan).'</em></li>';
        });

        return $rows->isEmpty()
            ? '<p>Jadwal belum pernah diubah.</p>'
            : '<ul class="space-y-3 text-sm">'.$rows->implode('').'</ul>';
    }

    /** @param  array<string, mixed>  $data */
    private static function ringkas(array $data): string
    {
        return collect($data)->only(['tanggal', 'waktu_mulai', 'waktu_selesai', 'lokasi', 'tautan_pertemuan'])
            ->filter(fn ($v) => filled($v))->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.$v)->implode('; ');
    }

    /** @return array<int, mixed> */
    private static function waktuFields(): array
    {
        return [
            DatePicker::make('tanggal')->required(),
            TimePicker::make('waktu_mulai')->label('Mulai')->seconds(false)->required(),
            TimePicker::make('waktu_selesai')->label('Selesai')->seconds(false)->required()->after('waktu_mulai'),
            Select::make('jenis_kegiatan_id')->label('Jenis kegiatan')->required()
                ->options(fn () => JenisKegiatan::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')->all()),
        ];
    }

    /**
     * Only Superadmin and Admin Pusat may choose an executor other than the responsible mentor.
     *
     * @param  Closure(): Sekolah  $sekolah
     * @return array<int, mixed>
     */
    private static function pelaksanaField(Closure $sekolah): array
    {
        return [
            Select::make('mentor_pelaksana_id')->label('Mentor pelaksana')->searchable()
                ->placeholder('Mentor penanggung jawab')
                ->helperText('Kosongkan untuk memakai mentor penanggung jawab sekolah.')
                ->options(fn () => self::mentorOptions()->all())
                ->visible(fn () => Gate::allows('assignPelaksanaBaru', $sekolah())),
        ];
    }

    /** @return Collection<int, string> */
    private static function mentorOptions(): Collection
    {
        return User::query()->where('role', Role::Mentor->value)->where('aktif', true)->orderBy('name')->pluck('name', 'id');
    }

    private static function metode(mixed $id): ?MetodeKegiatan
    {
        return filled($id) ? MetodeKegiatan::query()->find($id) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function input(array $data): array
    {
        foreach (['waktu_mulai', 'waktu_selesai'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = substr((string) $data[$key], 0, 5);
            }
        }

        return $data;
    }
}

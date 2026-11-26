<?php

namespace App\Filament\Admin\Resources\Sekolahs\Pages;

use App\Domain\Master\Models\Cabang;
use App\Domain\Mentor\Actions\AssignMentor;
use App\Domain\Sekolah\Actions\ArchiveSekolah;
use App\Domain\Sekolah\Actions\CorrectSekolahCabang;
use App\Domain\Sekolah\Actions\RecordSekolahMou;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Role;
use App\Filament\Admin\Resources\Sekolahs\SekolahResource;
use App\Filament\Support\RunsDomainAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * @property Sekolah $record
 */
class ViewSekolah extends ViewRecord
{
    protected static string $resource = SekolahResource::class;

    /** The record still comes from the scoped resource query (out of scope = 404). */
    protected function resolveRecord(int|string $key): Model
    {
        /** @var Sekolah $record */
        $record = parent::resolveRecord($key);

        return $record
            ->loadCount(Sekolah::ringkasanCounts())
            ->load([
                'cabang', 'kabupatenKota', 'kategori', 'narahubungUtama', 'sertifikat',
                'penugasanAktif.mentor', 'penugasan' => fn ($q) => $q->with('mentor')->latest('mulai'),
                'laporanRevisi' => fn ($q) => $q->with(['penulis', 'kegiatan']),
                'riwayatNarahubung' => fn ($q) => $q->with('pengubah'),
                'koreksiCabang' => fn ($q) => $q->with(['cabangLama', 'cabangBaru', 'pengoreksi']),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->assignMentorAction(),
            EditAction::make(),
            ActionGroup::make([
                $this->recordMouAction(),
                $this->revokeMouAction(),
                $this->correctCabangAction(),
                $this->archiveAction(),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }

    private function assignMentorAction(): Action
    {
        return Action::make('assignMentor')
            ->label(fn () => $this->record->penugasanAktif ? 'Ganti Mentor' : 'Tetapkan Mentor')
            ->icon('heroicon-o-user-plus')
            ->visible(fn () => Gate::allows('assignMentor', $this->record))
            ->modalDescription(fn () => $this->record->penugasanAktif
                ? 'Penugasan mentor saat ini akan diakhiri dan tetap tercatat di riwayat.'
                : null)
            ->schema([
                Select::make('mentor_id')->label('Mentor')->required()->searchable()
                    ->options(fn () => User::query()->where('role', Role::Mentor->value)->where('aktif', true)
                        ->when($this->record->penugasanAktif, fn ($q, $p) => $q->whereKeyNot($p->mentor_id))
                        ->orderBy('name')->pluck('name', 'id')->all()),
                Textarea::make('alasan')->label('Alasan penggantian')->rows(2)
                    ->visible(fn () => $this->record->penugasanAktif !== null),
            ])
            ->action(function (Action $action, array $data) {
                RunsDomainAction::run($action, fn () => app(AssignMentor::class)
                    ->handle(auth()->user(), $this->record, (int) $data['mentor_id'], $data['alasan'] ?? null), 'Mentor ditetapkan');
                $this->refreshRecord();
            });
    }

    private function recordMouAction(): Action
    {
        return Action::make('recordMou')
            ->label(fn () => $this->record->isMou() ? 'Perbarui MOU' : 'Catat MOU')
            ->icon('heroicon-o-document-check')
            ->visible(fn () => Gate::allows('recordMou', $this->record))
            ->fillForm(fn () => ['tanggal' => $this->record->mou_tanggal?->toDateString()])
            ->schema([
                FileUpload::make('dokumen_file')
                    ->label('Dokumen MOU (PDF)')
                    ->disk('local')
                    ->directory('mou')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->storeFileNamesIn('dokumen_nama_asli')
                    ->helperText('Unggah berkas MOU berformat PDF (maks. 10 MB). Sekolah berstatus "Sudah MOU" setelah dokumen diunggah.')
                    ->required(),
                Hidden::make('dokumen_nama_asli'),
                DatePicker::make('tanggal')->label('Tanggal MOU')->maxDate(today()),
            ])
            ->action(function (Action $action, array $data) {
                $filePath = is_array($data['dokumen_file']) ? array_values($data['dokumen_file'])[0] : $data['dokumen_file'];
                $namaAsli = filled($data['dokumen_nama_asli'] ?? null)
                    ? (string) $data['dokumen_nama_asli']
                    : basename((string) $filePath);

                RunsDomainAction::run($action, fn () => app(RecordSekolahMou::class)
                    ->handle(auth()->user(), $this->record, (string) $filePath, $namaAsli, $data['tanggal'] ?? null), 'MOU dicatat');
                $this->refreshRecord();
            });
    }

    private function revokeMouAction(): Action
    {
        return Action::make('revokeMou')
            ->label('Batalkan MOU')
            ->icon('heroicon-o-document-minus')
            ->color('danger')
            ->visible(fn () => $this->record->isMou() && Gate::allows('recordMou', $this->record))
            ->schema([Textarea::make('alasan')->label('Alasan pembatalan')->required()->rows(2)])
            ->action(function (Action $action, array $data) {
                RunsDomainAction::run($action, fn () => app(RecordSekolahMou::class)
                    ->revoke(auth()->user(), $this->record, (string) $data['alasan']), 'MOU dibatalkan');
                $this->refreshRecord();
            });
    }

    private function correctCabangAction(): Action
    {
        return Action::make('correctCabang')
            ->label('Koreksi Cabang')
            ->icon('heroicon-o-arrows-right-left')
            ->visible(fn () => Gate::allows('correctCabang', $this->record))
            ->modalDescription('Hanya untuk memperbaiki salah input. Sekolah yang sudah memiliki kegiatan tidak dapat dikoreksi.')
            ->schema([
                Select::make('cabang_id')->label('Cabang yang benar')->required()
                    ->options(fn () => Cabang::query()->where('aktif', true)->whereKeyNot($this->record->cabang_id)->orderBy('nama')->pluck('nama', 'id')->all()),
                Textarea::make('alasan')->label('Alasan koreksi')->required()->rows(2),
            ])
            ->action(function (Action $action, array $data) {
                RunsDomainAction::run($action, fn () => app(CorrectSekolahCabang::class)
                    ->handle(auth()->user(), $this->record, (int) $data['cabang_id'], (string) $data['alasan']), 'Cabang dikoreksi');
                $this->refreshRecord();
            });
    }

    private function archiveAction(): Action
    {
        return Action::make('archive')
            ->label('Arsipkan')
            ->icon('heroicon-o-archive-box')
            ->color('danger')
            ->visible(fn () => Gate::allows('archive', $this->record))
            ->modalDescription('Sekolah yang diarsipkan tidak dapat diubah lagi, tetapi datanya tetap tersimpan. Kegiatan yang belum selesai harus diselesaikan atau dibatalkan terlebih dahulu.')
            ->schema([Textarea::make('alasan')->label('Alasan arsip')->required()->rows(2)])
            ->action(function (Action $action, array $data) {
                RunsDomainAction::run($action, fn () => app(ArchiveSekolah::class)
                    ->handle(auth()->user(), $this->record, (string) $data['alasan']), 'Sekolah diarsipkan');
                $this->refreshRecord();
            });
    }

    private function refreshRecord(): void
    {
        /** @var Sekolah $record */
        $record = $this->resolveRecord($this->record->getKey());
        $this->record = $record;
    }
}

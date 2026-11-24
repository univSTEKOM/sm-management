<?php

namespace App\Filament\Mentor\Resources\Sekolahs\Pages;

use App\Domain\Sekolah\Actions\ProposeNarahubungChange;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\Sekolah;
use App\Filament\Mentor\Resources\Sekolahs\SekolahResource;
use App\Filament\Support\RunsDomainAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * @property Sekolah $record
 */
class ViewSekolah extends ViewRecord
{
    protected static string $resource = SekolahResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var Sekolah $record */
        $record = parent::resolveRecord($key);

        return $record
            ->loadCount(Sekolah::ringkasanCounts())
            ->load([
                'cabang', 'kabupatenKota', 'kategori', 'narahubungUtama', 'narahubungAktif', 'sertifikat',
                'penugasanAktif.mentor', 'penugasan' => fn ($q) => $q->with('mentor')->latest('mulai'),
                'laporanRevisi' => fn ($q) => $q->with(['penulis', 'kegiatan']),
                'riwayatNarahubung' => fn ($q) => $q->with('pengubah'),
                'koreksiCabang' => fn ($q) => $q->with(['cabangLama', 'cabangBaru', 'pengoreksi']),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('proposeNarahubung')
                ->label('Usulkan Perubahan Narahubung')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => Gate::allows('proposeNarahubung', $this->record))
                ->modalDescription('Usulan akan ditinjau Admin Pusat atau Admin Cabang. Data narahubung tidak berubah sampai usulan disetujui.')
                ->schema([
                    Select::make('narahubung_id')->label('Narahubung')
                        ->placeholder('Tambah narahubung baru')
                        ->options(fn () => $this->record->narahubungAktif->mapWithKeys(fn (Narahubung $n) => [$n->id => $n->nama.($n->is_utama ? ' (utama)' : '')])->all())
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            $current = $state ? $this->record->narahubungAktif->firstWhere('id', (int) $state) : null;
                            foreach (Narahubung::FIELDS as $field) {
                                $set($field, $current?->{$field});
                            }
                        }),
                    TextInput::make('nama')->required()->maxLength(255),
                    TextInput::make('jabatan')->maxLength(255),
                    TextInput::make('nomor_wa')->label('Nomor WhatsApp')->required()->tel()->maxLength(30),
                    TextInput::make('email')->email()->maxLength(255),
                ])
                ->action(function (Action $action, array $data) {
                    RunsDomainAction::run($action, function () use ($data) {
                        $narahubung = filled($data['narahubung_id'] ?? null)
                            ? $this->record->narahubungAktif->firstWhere('id', (int) $data['narahubung_id'])
                            : null;
                        app(ProposeNarahubungChange::class)->handle(auth()->user(), $this->record, $data, $narahubung);
                    }, 'Usulan dikirim');
                }),
        ];
    }
}

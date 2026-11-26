<?php

namespace App\Filament\Admin\Resources\Jadwals;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Master\Models\Cabang;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Admin\Resources\Jadwals\Pages\ListJadwals;
use App\Filament\Admin\Resources\Sekolahs\SekolahResource;
use App\Models\User;
use App\Support\Authorization\ScopeResolver;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Cross-school schedule (FR-JDA / FR-JTL overview). Read-only for every role: it only lists
 * activities inside the user's scope (Admin Cabang: own branch). Changes are made from the
 * school page through the domain actions, never from this list.
 */
class JadwalResource extends Resource
{
    protected static ?string $model = Kegiatan::class;

    protected static ?string $slug = 'jadwal';

    protected static ?string $modelLabel = 'Jadwal';

    protected static ?string $pluralModelLabel = 'Jadwal';

    protected static string|UnitEnum|null $navigationGroup = 'Jadwal';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        $query = parent::getEloquentQuery();
        ScopeResolver::applyToKegiatanQuery($query, $user, 'kegiatan.view');

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sekolah.cabang', 'jenisKegiatan', 'metode', 'mentorPelaksana']))
            ->defaultSort('tanggal', 'desc')
            ->recordUrl(fn (Kegiatan $record) => SekolahResource::getUrl('view', ['record' => $record->sekolah_id]))
            ->columns([
                TextColumn::make('tanggal')->date('d M Y')->sortable()
                    ->description(fn (Kegiatan $record) => substr((string) $record->waktu_mulai, 0, 5).'–'.substr((string) $record->waktu_selesai, 0, 5)),
                TextColumn::make('sekolah.nama')->label('Sekolah')->searchable(),
                TextColumn::make('sekolah.cabang.nama')->label('Cabang'),
                TextColumn::make('nama_sesi')->label('Sesi'),
                TextColumn::make('jenisKegiatan.nama')->label('Jenis'),
                TextColumn::make('metode.nama')->label('Metode'),
                // R-01: meeting link and location follow the detail permission.
                TextColumn::make('lokasi_atau_tautan')->label('Lokasi / tautan')->wrap()
                    ->state(fn (Kegiatan $record) => Gate::allows('viewDetail', $record) ? ($record->lokasi ?: $record->tautan_pertemuan) : null)
                    ->placeholder('-'),
                TextColumn::make('mentorPelaksana.name')->label('Mentor'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (StatusKegiatan $state) => $state->label())
                    ->color(fn (StatusKegiatan $state) => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(StatusKegiatan::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()),
                SelectFilter::make('mentor_pelaksana_id')->label('Mentor')->searchable()
                    ->options(fn () => User::query()->where('role', Role::Mentor->value)->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('cabang')->label('Cabang')
                    ->visible(fn () => auth()->user()?->role !== Role::AdminCabang)
                    ->options(fn () => Cabang::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, $id) => $q->whereHas('sekolah', fn (Builder $s) => $s->where('cabang_id', $id)))),
                Filter::make('rentang')->label('Tanggal')
                    ->schema([DatePicker::make('dari')->label('Dari')->native(false), DatePicker::make('sampai')->label('Sampai')->native(false)])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal', '<=', $d))),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListJadwals::route('/')];
    }

    /** @return array<string, string> */
    public static function tahapTabs(): array
    {
        return [Tahap::Awal->value => 'Pelatihan Awal', Tahap::TindakLanjut->value => 'Tindak Lanjut'];
    }
}

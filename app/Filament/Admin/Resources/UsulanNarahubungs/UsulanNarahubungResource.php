<?php

namespace App\Filament\Admin\Resources\UsulanNarahubungs;

use App\Domain\Sekolah\Actions\DecideNarahubungProposal;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\NarahubungUsulan;
use App\Filament\Admin\Resources\Sekolahs\SekolahResource;
use App\Filament\Admin\Resources\UsulanNarahubungs\Pages\ListUsulanNarahubungs;
use App\Filament\Support\RunsDomainAction;
use App\Support\Authorization\ScopeResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/** Review queue for mentors' contact proposals (BR-SCH-11, AC-SCH-22/23). */
class UsulanNarahubungResource extends Resource
{
    protected static ?string $model = NarahubungUsulan::class;

    protected static ?string $slug = 'usulan-narahubung';

    protected static ?string $modelLabel = 'Usulan Narahubung';

    protected static ?string $pluralModelLabel = 'Usulan Narahubung';

    protected static string|UnitEnum|null $navigationGroup = 'Sekolah Mitra';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereIn('status', [NarahubungUsulan::DIAJUKAN, NarahubungUsulan::PERLU_DITINJAU])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('sekolah', fn (Builder $q) => ScopeResolver::applyToSekolahQuery($q, auth()->user(), 'narahubung.decide'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sekolah', 'pengusul', 'pemutus', 'narahubung']))
            ->defaultSort('diajukan_at', 'desc')
            ->columns([
                TextColumn::make('sekolah.nama')->label('Sekolah')->searchable()
                    ->url(fn (NarahubungUsulan $record) => SekolahResource::getUrl('view', ['record' => $record->sekolah_id])),
                TextColumn::make('jenis')->label('Jenis')->badge()->color('gray')
                    ->state(fn (NarahubungUsulan $record) => $record->narahubung_id ? 'Ubah' : 'Tambah'),
                TextColumn::make('perubahan')->label('Usulan')->wrap()
                    ->state(fn (NarahubungUsulan $record) => self::describe($record)),
                TextColumn::make('pengusul.name')->label('Diusulkan oleh')
                    ->description(fn (NarahubungUsulan $record) => $record->diajukan_at?->format('d M Y H:i')),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => NarahubungUsulan::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        NarahubungUsulan::DISETUJUI => 'success',
                        NarahubungUsulan::DITOLAK => 'danger',
                        default => 'warning',
                    })
                    ->description(fn (NarahubungUsulan $record) => $record->alasan_penolakan),
                TextColumn::make('pemutus.name')->label('Diputuskan oleh')->placeholder('-')
                    ->description(fn (NarahubungUsulan $record) => $record->diputuskan_at?->format('d M Y H:i')),
            ])
            ->filters([
                SelectFilter::make('status')->options(NarahubungUsulan::LABELS)->default(NarahubungUsulan::DIAJUKAN),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (NarahubungUsulan $record) => self::describe($record))
                    ->visible(fn (NarahubungUsulan $record) => Gate::allows('decide', $record))
                    ->action(function (Action $action, NarahubungUsulan $record) {
                        RunsDomainAction::run($action, fn () => app(DecideNarahubungProposal::class)->handle(auth()->user(), $record, true), 'Usulan disetujui');
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (NarahubungUsulan $record) => Gate::allows('decide', $record))
                    ->schema([Textarea::make('alasan_penolakan')->label('Alasan penolakan')->required()->rows(2)])
                    ->action(function (Action $action, NarahubungUsulan $record, array $data) {
                        RunsDomainAction::run($action, fn () => app(DecideNarahubungProposal::class)
                            ->handle(auth()->user(), $record, false, (string) $data['alasan_penolakan']), 'Usulan ditolak');
                    }),
            ]);
    }

    /** "field: old → new" summary of a proposal. */
    public static function describe(NarahubungUsulan $usulan): string
    {
        $labels = ['nama' => 'Nama', 'jabatan' => 'Jabatan', 'nomor_wa' => 'WhatsApp', 'email' => 'Email'];
        $before = $usulan->data_sebelum ?? [];

        return collect(Narahubung::FIELDS)
            ->filter(fn (string $field) => ($usulan->data_usulan[$field] ?? null) !== ($before[$field] ?? null))
            ->map(fn (string $field) => $usulan->narahubung_id
                ? "{$labels[$field]}: ".($before[$field] ?? '-').' → '.($usulan->data_usulan[$field] ?? '-')
                : "{$labels[$field]}: ".($usulan->data_usulan[$field] ?? '-'))
            ->implode('; ');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsulanNarahubungs::route('/'),
        ];
    }
}

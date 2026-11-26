<?php

namespace App\Filament\Admin\Resources\Users;

use App\Domain\Master\Models\Cabang;
use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Pengguna\Actions\ActivateUser;
use App\Domain\Pengguna\Actions\DeactivateUser;
use App\Domain\Pengguna\Actions\ResetUserPassword;
use App\Domain\Pengguna\Actions\UpdateUser;
use App\Domain\Shared\Enums\Role;
use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\RunsDomainAction;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Account management (FR-USR-01/02, FR-MNT-01): Superadmin only through UserPolicy
 * (AC-USR-01). Accounts are deactivated, never deleted.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('email')->label('Email')->email()->required()->maxLength(255),
            TextInput::make('telepon')->label('Telepon')->tel()->maxLength(30),
            Select::make('role')
                ->label('Role')
                ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all())
                ->required()
                ->live()
                // A Superadmin cannot change the role of their own account.
                ->disabled(fn (?User $record) => $record !== null && $record->is(auth()->user())),
            Select::make('cabang_id')
                ->label('Cabang')
                ->options(fn (?User $record) => self::cabangOptions($record))
                ->searchable()
                ->visible(fn (Get $get) => in_array(self::roleValue($get('role')), [Role::AdminCabang->value, Role::Mentor->value], true))
                ->required(fn (Get $get) => self::roleValue($get('role')) === Role::AdminCabang->value)
                ->helperText(fn (Get $get) => self::roleValue($get('role')) === Role::Mentor->value ? 'Opsional, hanya sebagai informasi.' : null),
            TextInput::make('password')
                ->label('Password awal')
                ->password()
                ->revealable()
                ->required()
                ->minLength(8)
                ->helperText('Pengguna wajib menggantinya saat login pertama.')
                ->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('cabang'))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('role')->label('Role')->badge()->formatStateUsing(fn (Role $state) => $state->label())->color('gray'),
                TextColumn::make('cabang.nama')->label('Cabang')->placeholder('—'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
                TextColumn::make('last_login_at')->label('Login terakhir')->since()->placeholder('Belum pernah')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all()),
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->options(fn () => Cabang::query()->orderBy('nama')->pluck('nama', 'id')->all()),
                TernaryFilter::make('aktif')->label('Status')->trueLabel('Aktif')->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (User $record, array $data, EditAction $action) => RunsDomainAction::attempt(
                        $action,
                        fn () => app(UpdateUser::class)->handle(auth()->user(), $record, self::updateInput($record, $data)),
                    )),
                Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('gray')
                    ->visible(fn (User $record) => Gate::allows('resetPassword', $record))
                    ->modalDescription('Semua sesi pengguna akan diakhiri dan ia wajib mengganti password saat login berikutnya.')
                    ->schema([
                        TextInput::make('password')
                            ->label('Password sementara')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->same('password_confirmation'),
                        TextInput::make('password_confirmation')
                            ->label('Konfirmasi password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false),
                    ])
                    ->action(fn (User $record, array $data, Action $action) => RunsDomainAction::run(
                        $action,
                        fn () => app(ResetUserPassword::class)->handle(auth()->user(), $record, $data['password']),
                        'Password direset',
                    )),
                Action::make('nonaktifkan')
                    ->label('Nonaktifkan')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => Gate::allows('deactivate', $record))
                    ->modalDescription(fn (User $record) => self::deactivationWarning($record))
                    ->action(fn (User $record, Action $action) => RunsDomainAction::run(
                        $action,
                        fn () => app(DeactivateUser::class)->handle(auth()->user(), $record),
                        'Akun dinonaktifkan',
                    )),
                Action::make('aktifkan')
                    ->label('Aktifkan')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => Gate::allows('activate', $record))
                    ->action(fn (User $record, Action $action) => RunsDomainAction::run(
                        $action,
                        fn () => app(ActivateUser::class)->handle(auth()->user(), $record),
                        'Akun diaktifkan',
                    )),
            ])
            ->toolbarActions([])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }

    /**
     * Role and branch are only sent when they changed, so editing one's own name never
     * requires `roles.assign` on oneself.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function updateInput(User $record, array $data): array
    {
        $input = array_intersect_key($data, array_flip(['name', 'email', 'telepon']));
        $role = self::roleValue($data['role'] ?? $record->role->value);
        $cabangId = filled($data['cabang_id'] ?? null) ? (int) $data['cabang_id'] : null;

        if ($role !== $record->role->value || $cabangId !== $record->cabang_id) {
            $input['role'] = $role;
            $input['cabang_id'] = $cabangId;
        }

        return $input;
    }

    /** @return array<int, string> */
    private static function cabangOptions(?User $record): array
    {
        return Cabang::query()
            ->where(fn ($q) => $q->where('aktif', true)->when($record?->cabang_id, fn ($q, $id) => $q->orWhere('id', $id)))
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    private static function roleValue(mixed $role): ?string
    {
        return $role instanceof Role ? $role->value : $role;
    }

    /** BR-MNT-04: warn before deactivating a mentor who still has active assignments. */
    private static function deactivationWarning(User $record): string
    {
        $aktif = $record->role === Role::Mentor
            ? PenugasanMentor::query()->where('mentor_id', $record->id)->whereNull('selesai')->count()
            : 0;

        return $aktif > 0
            ? "Mentor ini masih menjadi penanggung jawab aktif untuk {$aktif} sekolah. Tugaskan mentor lain terlebih dahulu; penonaktifan akan ditolak."
            : 'Akun tidak dapat login lagi dan semua sesinya diakhiri. Riwayat aktivitas tetap tersimpan.';
    }
}

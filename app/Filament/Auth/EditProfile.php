<?php

namespace App\Filament\Auth;

use App\Domain\Pengguna\Actions\ChangeOwnPassword;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Profil & Ganti Password (FR-AUTH-03). Account data (name, email, role) is managed by
 * Superadmin only (BR-USR-01), so users can only change their own password here.
 * Saving goes through ChangeOwnPassword, which also clears the first-login obligation.
 *
 * The new password uses its own field names: the base page would otherwise store the
 * submitted `password` value as the session password hash used by AuthenticateSession.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun')
                ->description('Data akun hanya dapat diubah oleh Superadmin.')
                ->schema([
                    TextInput::make('name')->label('Nama')->disabled()->dehydrated(false),
                    TextInput::make('email')->label('Email')->disabled()->dehydrated(false),
                ]),
            Section::make('Ganti password')
                ->description($this->getUser()->getAttribute('wajib_ganti_password')
                    ? 'Anda wajib mengganti password awal sebelum memakai aplikasi.'
                    : null)
                ->schema([
                    TextInput::make('currentPassword')
                        ->label('Password saat ini')
                        ->password()
                        ->revealable()
                        ->autocomplete('current-password')
                        ->required(),
                    TextInput::make('newPassword')
                        ->label('Password baru')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->required()
                        ->minLength(8)
                        ->same('newPasswordConfirmation'),
                    TextInput::make('newPasswordConfirmation')
                        ->label('Konfirmasi password baru')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->required()
                        ->dehydrated(false),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        try {
            app(ChangeOwnPassword::class)->handle($record, (string) $data['currentPassword'], (string) $data['newPassword']);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('data.'.($field === 'password' ? 'newPassword' : $field), implode(' ', $messages));
            }

            throw new Halt;
        }

        // Keep this session valid for AuthenticateSession; other sessions end because the hash changed.
        if (request()->hasSession()) {
            $guard = Filament::auth();
            $hash = method_exists($guard, 'hashPasswordForCookie')
                ? $guard->hashPasswordForCookie($record->getAuthPassword())
                : $record->getAuthPassword();

            request()->session()->put('password_hash_'.Filament::getAuthGuard(), $hash);
        }

        $this->data['currentPassword'] = null;
        $this->data['newPassword'] = null;
        $this->data['newPasswordConfirmation'] = null;

        return $record;
    }

    protected function getRedirectUrl(): ?string
    {
        return filament()->getUrl();
    }
}

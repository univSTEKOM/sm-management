<?php

namespace App\Models;

use App\Domain\Master\Models\Cabang;
use App\Domain\Shared\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property int|null $cabang_id
 * @property bool $aktif
 * @property bool $wajib_ganti_password
 */
#[Fillable(['name', 'email', 'password', 'telepon', 'role', 'cabang_id', 'aktif', 'wajib_ganti_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PANEL_ADMIN = 'admin';

    public const PANEL_MENTOR = 'mentor';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'aktif' => 'boolean',
            'wajib_ganti_password' => 'boolean',
            'dikunci_sampai' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Cabang, $this> */
    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    /** TD-12: mentors use the mentor panel; Superadmin, Admin Pusat and Admin Cabang use the admin panel. */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->aktif) {
            return false;
        }

        return match ($panel->getId()) {
            self::PANEL_ADMIN => $this->role->isAdmin(),
            self::PANEL_MENTOR => $this->role === Role::Mentor,
            default => false,
        };
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }
}

<?php

/*
 * Fase 2 / T-11: account actions (FR-USR-01/02, FR-MNT-01, FR-AUTH-03, BR-USR-01,
 * BR-MNT-04, AC-USR-01, AC-USR-03, AC-AUTH-03).
 */

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Laporan\Actions\ReportKegiatan;
use App\Domain\Laporan\Models\LaporanRevisi;
use App\Domain\Master\Models\Cabang;
use App\Domain\Pengguna\Actions\ActivateUser;
use App\Domain\Pengguna\Actions\ChangeOwnPassword;
use App\Domain\Pengguna\Actions\CreateUser;
use App\Domain\Pengguna\Actions\DeactivateUser;
use App\Domain\Pengguna\Actions\ResetUserPassword;
use App\Domain\Pengguna\Actions\UpdateUser;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->sa = User::factory()->superadmin()->create();
    $this->cabang = Cabang::factory()->create();
});

function fakeSession(User $user): void
{
    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('x'),
        'last_activity' => now()->getTimestamp(),
    ]);
}

test('FR-USR-01 superadmin creates an account that must change the initial password', function () {
    $user = app(CreateUser::class)->handle($this->sa, [
        'name' => ' Budi Mentor ',
        'email' => ' Budi@Example.TEST ',
        'telepon' => '08123',
        'role' => Role::Mentor->value,
        'password' => 'rahasia123',
    ]);

    expect($user->name)->toBe('Budi Mentor')
        ->and($user->email)->toBe('budi@example.test')
        ->and($user->role)->toBe(Role::Mentor)
        ->and($user->aktif)->toBeTrue()
        ->and($user->wajib_ganti_password)->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();

    $audit = AuditLog::where('aksi', 'user.create')->sole();
    expect($audit->sesudah)->toMatchArray(['email' => 'budi@example.test', 'role' => 'mentor'])
        ->and(json_encode($audit->sesudah))->not->toContain('rahasia123')
        ->and(json_encode($audit->sesudah))->not->toContain('password');
});

test('FR-USR-01 admin cabang requires a branch; other admin roles carry none', function () {
    expect(fn () => app(CreateUser::class)->handle($this->sa, [
        'name' => 'AC', 'email' => 'ac@example.test', 'role' => Role::AdminCabang->value, 'password' => 'rahasia123',
    ]))->toThrow(ValidationException::class);

    $ac = app(CreateUser::class)->handle($this->sa, [
        'name' => 'AC', 'email' => 'ac@example.test', 'role' => Role::AdminCabang->value, 'cabang_id' => $this->cabang->id, 'password' => 'rahasia123',
    ]);
    $ap = app(CreateUser::class)->handle($this->sa, [
        'name' => 'AP', 'email' => 'ap@example.test', 'role' => Role::AdminPusat->value, 'cabang_id' => $this->cabang->id, 'password' => 'rahasia123',
    ]);

    expect($ac->cabang_id)->toBe($this->cabang->id)->and($ap->cabang_id)->toBeNull();
});

test('inactive branches are not offered for new accounts', function () {
    $this->cabang->update(['aktif' => false]);

    expect(fn () => app(CreateUser::class)->handle($this->sa, [
        'name' => 'AC', 'email' => 'ac@example.test', 'role' => Role::AdminCabang->value, 'cabang_id' => $this->cabang->id, 'password' => 'rahasia123',
    ]))->toThrow(ValidationException::class);
});

test('emails are unique case-insensitively and passwords need 8 characters', function () {
    User::factory()->create(['email' => 'ada@example.test']);

    expect(fn () => app(CreateUser::class)->handle($this->sa, [
        'name' => 'X', 'email' => 'ADA@example.test', 'role' => Role::Mentor->value, 'password' => 'rahasia123',
    ]))->toThrow(ValidationException::class)
        ->and(fn () => app(CreateUser::class)->handle($this->sa, [
            'name' => 'X', 'email' => 'baru@example.test', 'role' => Role::Mentor->value, 'password' => 'pendek',
        ]))->toThrow(ValidationException::class);
});

test('AC-USR-01 BR-USR-01 only superadmin manages accounts, including mentor accounts', function (string $role) {
    $actor = match ($role) {
        'admin_pusat' => User::factory()->adminPusat()->create(),
        'admin_cabang' => User::factory()->adminCabang($this->cabang)->create(),
        'mentor' => User::factory()->mentor()->create(),
    };
    $mentor = User::factory()->mentor()->create(['name' => 'Asli']);

    expect(fn () => app(CreateUser::class)->handle($actor, ['name' => 'X', 'email' => 'x@example.test', 'role' => 'mentor', 'password' => 'rahasia123']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateUser::class)->handle($actor, $mentor, ['name' => 'Diubah']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeactivateUser::class)->handle($actor, $mentor))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ResetUserPassword::class)->handle($actor, $mentor, 'rahasia123'))->toThrow(AuthorizationException::class);

    expect($mentor->fresh()->name)->toBe('Asli')->and($mentor->fresh()->aktif)->toBeTrue();
})->with(['admin_pusat', 'admin_cabang', 'mentor']);

test('superadmin updates account data with before/after audit', function () {
    $user = User::factory()->adminPusat()->create(['name' => 'Lama', 'email' => 'lama@example.test']);

    app(UpdateUser::class)->handle($this->sa, $user, ['name' => 'Baru', 'email' => 'BARU@example.test', 'telepon' => '0899']);

    expect($user->fresh())->name->toBe('Baru')->email->toBe('baru@example.test');
    $audit = AuditLog::where('aksi', 'user.update')->sole();
    expect($audit->sebelum['name'])->toBe('Lama')->and($audit->sesudah['name'])->toBe('Baru');
});

test('role change ends sessions and admin cabang role needs a branch', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->adminPusat()->create();
    fakeSession($user);

    expect(fn () => app(UpdateUser::class)->handle($this->sa, $user, ['role' => Role::AdminCabang->value]))
        ->toThrow(ValidationException::class);

    app(UpdateUser::class)->handle($this->sa, $user, ['role' => Role::AdminCabang->value, 'cabang_id' => $this->cabang->id]);

    expect($user->fresh())->role->toBe(Role::AdminCabang)->cabang_id->toBe($this->cabang->id)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('moving an admin cabang to another branch ends its sessions', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->adminCabang($this->cabang)->create();
    fakeSession($user);

    app(UpdateUser::class)->handle($this->sa, $user, ['cabang_id' => Cabang::factory()->create()->id]);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('superadmin cannot change own role, deactivate or reset own account', function () {
    expect(fn () => app(UpdateUser::class)->handle($this->sa, $this->sa, ['role' => Role::AdminPusat->value]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeactivateUser::class)->handle($this->sa, $this->sa))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ResetUserPassword::class)->handle($this->sa, $this->sa, 'rahasia123'))->toThrow(AuthorizationException::class);

    // Own name may still be edited.
    app(UpdateUser::class)->handle($this->sa, $this->sa, ['name' => 'SA Baru', 'role' => Role::Superadmin->value]);
    expect($this->sa->fresh()->name)->toBe('SA Baru');
});

test('an inactive superadmin cannot act; an active one may demote another superadmin', function () {
    // The "last active Superadmin" guard in AccountRules is defensive: the acting Superadmin
    // must be active and cannot target itself, so another active Superadmin always remains.
    $other = User::factory()->superadmin()->create();
    $inactive = User::factory()->superadmin()->nonaktif()->create();

    expect(fn () => app(UpdateUser::class)->handle($inactive, $other, ['role' => Role::AdminPusat->value]))->toThrow(AuthorizationException::class);

    app(UpdateUser::class)->handle($this->sa, $other, ['role' => Role::AdminPusat->value]);
    expect($other->fresh()->role)->toBe(Role::AdminPusat);
});

test('FR-USR-02 deactivation keeps the account, ends sessions and rotates the remember token', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->adminPusat()->create();
    $token = $user->remember_token;
    fakeSession($user);
    fakeSession($user);

    app(DeactivateUser::class)->handle($this->sa, $user);

    expect($user->fresh())->not->toBeNull()
        ->and($user->fresh()->aktif)->toBeFalse()
        ->and($user->fresh()->remember_token)->not->toBe($token)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

    $audit = AuditLog::where('aksi', 'user.deactivate')->sole();
    expect($audit->sesudah)->toBe(['aktif' => false, 'sesi_dicabut' => 2]);
});

test('BR-MNT-04 a mentor with active assignments cannot be deactivated or change role until reassigned', function () {
    $mentor = User::factory()->mentor()->create();
    $penugasan = Fixtures::assign(Fixtures::sekolah($this->cabang, null, ['nama' => 'SMA Penugasan']), $mentor);

    try {
        app(DeactivateUser::class)->handle($this->sa, $mentor);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('SMA Penugasan');
    }

    expect(fn () => app(UpdateUser::class)->handle($this->sa, $mentor, ['role' => Role::AdminPusat->value]))->toThrow(ValidationException::class);
    expect($mentor->fresh()->aktif)->toBeTrue()->and($mentor->fresh()->role)->toBe(Role::Mentor);

    $penugasan->update(['selesai' => now()]);
    app(DeactivateUser::class)->handle($this->sa, $mentor);
    expect($mentor->fresh()->aktif)->toBeFalse();
});

test('AC-USR-03 a deactivated mentor still owns the reports written earlier', function () {
    $mentor = User::factory()->mentor()->create(['name' => 'Mentor Lama']);
    $sekolah = Fixtures::sekolah($this->cabang, $mentor);
    $kegiatan = Fixtures::kegiatan($sekolah, $mentor, ['tanggal' => now()->subDay()->toDateString()]);
    app(ReportKegiatan::class)->handle($mentor, $kegiatan, StatusKegiatan::Selesai, 'Pelatihan selesai.');

    $sekolah->penugasanAktif()->update(['selesai' => now()]);
    app(DeactivateUser::class)->handle($this->sa, $mentor);

    $revisi = LaporanRevisi::where('kegiatan_id', $kegiatan->id)->with('penulis')->sole();
    expect($revisi->penulis->name)->toBe('Mentor Lama')->and($revisi->penulis->aktif)->toBeFalse();
    expect(AuditLog::where('user_id', $mentor->id)->exists())->toBeTrue();
});

test('reactivation restores access and refuses an admin cabang whose branch is inactive', function () {
    $user = User::factory()->adminCabang($this->cabang)->nonaktif()->create();
    $this->cabang->update(['aktif' => false]);

    expect(fn () => app(ActivateUser::class)->handle($this->sa, $user))->toThrow(ValidationException::class);

    $this->cabang->update(['aktif' => true]);
    app(ActivateUser::class)->handle($this->sa, $user);
    expect($user->fresh()->aktif)->toBeTrue()
        ->and(AuditLog::where('aksi', 'user.activate')->count())->toBe(1);
});

test('FR-AUTH-03 reset password forces a change, clears lockout counters and ends sessions without auditing the secret', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->mentor()->create(['gagal_login' => 4, 'dikunci_sampai' => now()->addMinutes(5)]);
    fakeSession($user);

    app(ResetUserPassword::class)->handle($this->sa, $user, 'sementara99');

    $fresh = $user->fresh();
    expect(Hash::check('sementara99', $fresh->password))->toBeTrue()
        ->and($fresh->wajib_ganti_password)->toBeTrue()
        ->and($fresh->getAttribute('gagal_login'))->toBe(0)
        ->and($fresh->getAttribute('dikunci_sampai'))->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

    $audit = AuditLog::where('aksi', 'user.reset_password')->sole();
    expect(json_encode([$audit->sebelum, $audit->sesudah]))->not->toContain('sementara99');
});

test('FR-AUTH-03 a user changes their own password and clears the first-login obligation', function () {
    $user = User::factory()->mentor()->create(['wajib_ganti_password' => true]);

    expect(fn () => app(ChangeOwnPassword::class)->handle($user, 'salah', 'passwordbaru1'))->toThrow(ValidationException::class)
        ->and(fn () => app(ChangeOwnPassword::class)->handle($user, 'password', 'password'))->toThrow(ValidationException::class)
        ->and(fn () => app(ChangeOwnPassword::class)->handle($user, 'password', 'pendek'))->toThrow(ValidationException::class);

    app(ChangeOwnPassword::class)->handle($user, 'password', 'passwordbaru1');

    expect(Hash::check('passwordbaru1', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->wajib_ganti_password)->toBeFalse()
        ->and(AuditLog::where('aksi', 'user.password_change')->where('user_id', $user->id)->count())->toBe(1);
});

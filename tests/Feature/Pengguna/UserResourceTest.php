<?php

/*
 * Fase 2 / T-11 UI: account management is Superadmin only (AC-USR-01), deactivation ends
 * access (AC-AUTH-03), and the profile page clears the first-login password obligation.
 */

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Master\Models\Cabang;
use App\Domain\Shared\Enums\Role;
use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\Auth\EditProfile;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\Fixtures;
use Tests\Support\LivewireHttp;

beforeEach(function () {
    $this->sa = User::factory()->superadmin()->create();
    $this->cabang = Cabang::factory()->create(['nama' => 'Cabang Semarang']);
});

test('superadmin opens the account page', function () {
    $this->actingAs($this->sa)->get('/admin/pengguna')->assertOk()->assertSee($this->sa->email);
});

test('AC-USR-01 admin pusat and admin cabang get 403 on the account page', function (string $role) {
    $user = $role === 'admin_pusat' ? User::factory()->adminPusat()->create() : User::factory()->adminCabang($this->cabang)->create();

    $this->actingAs($user)->get('/admin/pengguna')->assertForbidden();
})->with(['admin_pusat', 'admin_cabang']);

test('AC-USR-01 the account page component refuses admin pusat even when mounted directly', function () {
    $this->actingAs(User::factory()->adminPusat()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageUsers::class)->assertForbidden();
});

test('FR-MNT-01 superadmin creates a mentor account through the resource', function () {
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageUsers::class)
        ->callAction('create', data: [
            'name' => 'Mentor Baru',
            'email' => 'mentor.baru@example.test',
            'role' => Role::Mentor->value,
            'cabang_id' => $this->cabang->id,
            'password' => 'awalan123',
        ])
        ->assertHasNoActionErrors();

    $mentor = User::where('email', 'mentor.baru@example.test')->sole();
    expect($mentor->role)->toBe(Role::Mentor)
        ->and($mentor->cabang_id)->toBe($this->cabang->id)
        ->and($mentor->wajib_ganti_password)->toBeTrue();
});

test('admin cabang account requires a branch in the form', function () {
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageUsers::class)
        ->callAction('create', data: [
            'name' => 'AC', 'email' => 'ac@example.test', 'role' => Role::AdminCabang->value, 'password' => 'awalan123',
        ])
        ->assertHasActionErrors(['cabang_id']);

    expect(User::where('email', 'ac@example.test')->exists())->toBeFalse();
});

test('superadmin edits an account and changes its role', function () {
    $user = User::factory()->adminPusat()->create();
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageUsers::class)
        ->callTableAction('edit', $user, data: [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'role' => Role::AdminCabang->value,
            'cabang_id' => $this->cabang->id,
        ])
        ->assertHasNoTableActionErrors();

    expect($user->fresh())->name->toBe('Nama Baru')->role->toBe(Role::AdminCabang)->cabang_id->toBe($this->cabang->id);
});

test('superadmin edits own name without role-assignment on self', function () {
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageUsers::class)
        ->callTableAction('edit', $this->sa, data: ['name' => 'Super Baru', 'email' => $this->sa->email])
        ->assertHasNoTableActionErrors()
        ->assertTableActionHidden('nonaktifkan', $this->sa)
        ->assertTableActionHidden('resetPassword', $this->sa);

    expect($this->sa->fresh())->name->toBe('Super Baru')->role->toBe(Role::Superadmin);
});

test('superadmin resets a password from the table', function () {
    $user = User::factory()->mentor()->create();
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageUsers::class)
        ->callTableAction('resetPassword', $user, data: ['password' => 'sementara1', 'password_confirmation' => 'sementara1'])
        ->assertHasNoTableActionErrors();

    expect(Hash::check('sementara1', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->wajib_ganti_password)->toBeTrue();
});

test('BR-MNT-04 deactivating a mentor with active assignments is refused from the table', function () {
    $mentor = User::factory()->mentor()->create();
    Fixtures::sekolah($this->cabang, $mentor);
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageUsers::class)
        ->mountTableAction('nonaktifkan', $mentor)
        ->assertMountedActionModalSee('masih menjadi penanggung jawab aktif untuk 1 sekolah')
        ->callMountedTableAction()
        ->assertNotified('Tidak dapat disimpan');

    expect($mentor->fresh()->aktif)->toBeTrue();
});

test('FR-USR-02 superadmin deactivates and reactivates an account from the table', function () {
    $user = User::factory()->adminPusat()->create();
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageUsers::class)
        ->callTableAction('nonaktifkan', $user)
        ->assertTableActionVisible('aktifkan', $user);
    expect($user->fresh()->aktif)->toBeFalse();

    Livewire::test(ManageUsers::class)->callTableAction('aktifkan', $user);
    expect($user->fresh()->aktif)->toBeTrue();
});

test('AC-AUTH-03 a deactivated account cannot log in and the attempt is audited', function () {
    $user = User::factory()->mentor()->create(['email' => 'nonaktif@example.test']);
    AuditLog::create(['occurred_at' => now()->subDay(), 'user_id' => $user->id, 'role_saat_aksi' => 'mentor', 'aksi' => 'login']);
    $user->update(['aktif' => false]);

    Filament::setCurrentPanel('mentor');
    Livewire::test(Login::class)
        ->fillForm(['email' => 'nonaktif@example.test', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
    $gagal = AuditLog::where('aksi', 'login_gagal')->latest('id')->first();
    expect($gagal->user_id)->toBe($user->id)
        ->and($gagal->sesudah['alasan'])->toBe('akun_nonaktif')
        // Earlier activity of the account remains in the audit trail.
        ->and(AuditLog::where('user_id', $user->id)->where('aksi', 'login')->exists())->toBeTrue();
});

test('AC-AUTH-03 a session of a deactivated account loses panel access', function () {
    $user = User::factory()->adminPusat()->nonaktif()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('first login redirects to the profile until the password is changed', function () {
    $user = User::factory()->mentor()->create(['wajib_ganti_password' => true]);

    $this->actingAs($user)->get('/mentor/kegiatan-saya')->assertRedirect('/mentor/profile');
    $this->actingAs($user)->get('/mentor/profile')->assertOk()->assertSee('wajib mengganti password awal');
});

test('FR-AUTH-03 the profile page changes the own password and clears the obligation', function () {
    $user = User::factory()->mentor()->create(['wajib_ganti_password' => true]);
    $this->actingAs($user);
    Filament::setCurrentPanel('mentor');

    Livewire::test(EditProfile::class)
        ->fillForm(['currentPassword' => 'salah', 'newPassword' => 'barubaru1', 'newPasswordConfirmation' => 'barubaru1'])
        ->call('save')
        ->assertHasErrors(['data.currentPassword']);
    expect($user->fresh()->wajib_ganti_password)->toBeTrue();

    Livewire::test(EditProfile::class)
        ->fillForm(['currentPassword' => 'password', 'newPassword' => 'barubaru1', 'newPasswordConfirmation' => 'barubaru1'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $fresh = $user->fresh();
    expect(Hash::check('barubaru1', $fresh->password))->toBeTrue()
        ->and($fresh->wajib_ganti_password)->toBeFalse();
});

test('FR-AUTH-03 changing the password over HTTP keeps the current session valid (AuthenticateSession)', function () {
    $user = User::factory()->mentor()->create(['wajib_ganti_password' => true]);
    $page = $this->actingAs($user)->get('/mentor/profile')->assertOk();
    $cookie = LivewireHttp::sessionCookie($page);
    $snapshot = LivewireHttp::snapshot($page->getContent(), EditProfile::class);
    LivewireHttp::freshRequestState();

    $saved = $this->withCookie(config('session.cookie'), $cookie)
        ->withHeaders(['X-Livewire' => '1'])
        ->postJson(LivewireHttp::updateUri(), ['components' => [LivewireHttp::component($snapshot, [
            'data.currentPassword' => 'password',
            'data.newPassword' => 'barubaru1',
            'data.newPasswordConfirmation' => 'barubaru1',
        ], [LivewireHttp::call('save')])]])
        ->assertOk();
    $cookie = LivewireHttp::sessionCookie($saved) ?? $cookie;

    expect($user->fresh()->wajib_ganti_password)->toBeFalse();

    // actingAs() pins the PHP object; a real request reloads the user, so use the fresh record.
    LivewireHttp::freshRequestState();
    $response = $this->actingAs($user->fresh())->withCookie(config('session.cookie'), $cookie)->get('/mentor/kegiatan-saya');
    expect($response->headers->get('Location'))->toBeNull();
    $response->assertOk();
});

test('the profile page does not let users change their own name or email', function () {
    $user = User::factory()->mentor()->create(['name' => 'Nama Asli']);
    $this->actingAs($user);
    Filament::setCurrentPanel('mentor');

    Livewire::test(EditProfile::class)
        ->set('data.name', 'Nama Palsu')
        ->set('data.email', 'palsu@example.test')
        ->fillForm(['currentPassword' => 'password', 'newPassword' => 'barubaru1', 'newPasswordConfirmation' => 'barubaru1'])
        ->call('save');

    expect($user->fresh())->name->toBe('Nama Asli')->email->not->toBe('palsu@example.test');
});

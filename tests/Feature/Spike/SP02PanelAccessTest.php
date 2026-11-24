<?php

/*
 * SP-02: two Filament panels share one users table with correct role limits.
 */

use App\Domain\Master\Models\Cabang;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('mentor is refused on the admin panel and admins are refused on the mentor panel', function (string $state, string $url, int $status) {
    $user = match ($state) {
        'superadmin' => User::factory()->superadmin()->create(),
        'admin_pusat' => User::factory()->adminPusat()->create(),
        'admin_cabang' => User::factory()->adminCabang(Cabang::factory()->create())->create(),
        'mentor' => User::factory()->mentor()->create(),
    };

    $this->actingAs($user)->get($url)->assertStatus($status);
})->with([
    'superadmin → admin' => ['superadmin', '/admin', 200],
    'admin_pusat → admin' => ['admin_pusat', '/admin', 200],
    'admin_cabang → admin' => ['admin_cabang', '/admin', 200],
    'mentor → admin' => ['mentor', '/admin', 403],
    'superadmin → mentor' => ['superadmin', '/mentor', 403],
    'admin_pusat → mentor' => ['admin_pusat', '/mentor', 403],
    'admin_cabang → mentor' => ['admin_cabang', '/mentor', 403],
    'mentor → mentor' => ['mentor', '/mentor', 200],
]);

test('inactive accounts are refused on both panels', function (string $url, string $role) {
    $user = $role === 'mentor'
        ? User::factory()->mentor()->nonaktif()->create()
        : User::factory()->adminPusat()->nonaktif()->create();

    $this->actingAs($user)->get($url)->assertForbidden();
})->with([
    ['/admin', 'admin_pusat'],
    ['/mentor', 'mentor'],
]);

test('guests are redirected to the panel login', function (string $url, string $login) {
    $this->get($url)->assertRedirect($login);
})->with([
    ['/admin', '/admin/login'],
    ['/mentor', '/mentor/login'],
]);

test('mentor work page is not reachable from the admin panel role', function () {
    $this->actingAs(User::factory()->adminPusat()->create())
        ->get('/mentor/kegiatan-saya')
        ->assertForbidden();
});

test('one users table backs both panels', function () {
    $admin = User::factory()->adminPusat()->create(['email' => 'admin@example.test']);
    $mentor = User::factory()->mentor()->create(['email' => 'mentor@example.test']);

    expect(User::query()->whereIn('id', [$admin->id, $mentor->id])->count())->toBe(2);
    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->actingAs($mentor)->get('/mentor')->assertOk();
});

test('email uniqueness is case-insensitive', function () {
    User::factory()->create(['email' => 'Sama@Example.test']);

    expect(fn () => User::factory()->create(['email' => 'sama@example.test']))
        ->toThrow(UniqueConstraintViolationException::class);
});

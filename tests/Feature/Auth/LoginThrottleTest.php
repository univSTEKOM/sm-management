<?php

/*
 * TD-09 / AC-AUTH-02 / S-04: login lockout per account, per account + IP and per IP.
 */

use App\Domain\Audit\Models\AuditLog;
use App\Filament\Auth\Login;
use App\Models\User;
use App\Support\Auth\LoginThrottle;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Filament::setCurrentPanel('admin');
});

function attempt(string $email, string $password = 'salah'): Testable
{
    return Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate');
}

test('AC-AUTH-02 a wrong password shows the generic message and is audited', function () {
    $user = User::factory()->adminPusat()->create(['email' => 'ap@example.test']);

    attempt('ap@example.test')->assertHasFormErrors(['email']);
    attempt('tidak-ada@example.test')->assertHasFormErrors(['email']);

    expect(Login::MESSAGE)->toBe('Email/username atau password salah.')
        ->and(AuditLog::where('aksi', 'login_gagal')->count())->toBe(2);

    $this->assertGuest();
    // Same wording for an existing and an unknown account: no enumeration.
    attempt('ap@example.test')->assertHasFormErrors(['email' => Login::MESSAGE]);
    attempt('tidak-ada@example.test')->assertHasFormErrors(['email' => Login::MESSAGE]);
    expect($user->fresh()->gagal_login)->toBe(2);
});

test('TD-09 five failures lock the account for 15 minutes, even with the right password', function () {
    $user = User::factory()->adminPusat()->create(['email' => 'ap@example.test']);

    foreach (range(1, 5) as $i) {
        attempt('ap@example.test');
    }

    $fresh = $user->fresh();
    expect($fresh->gagal_login)->toBe(5)
        ->and($fresh->dikunci_sampai)->not->toBeNull()
        ->and((int) now()->diffInMinutes($fresh->dikunci_sampai, true))->toBeGreaterThanOrEqual(14);

    attempt('ap@example.test', 'password');
    $this->assertGuest();
    expect(AuditLog::where('aksi', 'login_diblokir')->exists())->toBeTrue();
});

test('TD-09 the lock expires after 15 minutes and a successful login resets the counter', function () {
    $user = User::factory()->adminPusat()->create(['email' => 'ap@example.test']);
    foreach (range(1, 5) as $i) {
        attempt('ap@example.test');
    }

    $this->travel(16)->minutes();
    Cache::flush(); // the per-pair counter has expired by then as well

    attempt('ap@example.test', 'password');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->gagal_login)->toBe(0)->and($user->fresh()->dikunci_sampai)->toBeNull();
});

test('S-04 an expired lock starts a fresh count instead of locking again after one failure', function () {
    $user = User::factory()->adminPusat()->create(['email' => 'ap@example.test']);
    $user->forceFill(['gagal_login' => 5, 'dikunci_sampai' => now()->subMinute()])->save();

    attempt('ap@example.test');

    expect($user->fresh()->gagal_login)->toBe(1);
});

test('S-04 attempts against an unknown email are limited without revealing that it is unknown', function () {
    foreach (range(1, 5) as $i) {
        attempt('hantu@example.test');
    }

    expect(LoginThrottle::secondsBlocked('hantu@example.test', '127.0.0.1'))->toBeGreaterThan(0);
    attempt('hantu@example.test', 'password');
    expect(AuditLog::where('aksi', 'login_diblokir')->exists())->toBeTrue();
});

test('S-04 users behind one public IP do not block each other', function () {
    $users = collect(range(1, 6))->map(fn ($i) => User::factory()->adminPusat()->create(['email' => "staf{$i}@sekolah.test"]));

    // Each colleague mistypes twice (12 failures from the same IP in total) ...
    foreach ($users as $user) {
        attempt($user->email);
        attempt($user->email);
    }

    // ... and every one of them can still log in.
    foreach ($users as $user) {
        expect(LoginThrottle::secondsBlocked($user->email, '127.0.0.1'))->toBe(0);
        auth()->logout();
        attempt($user->email, 'password');
        $this->assertAuthenticatedAs($user);
    }
});

test('S-04 the lock of one account does not affect another account on the same IP', function () {
    $a = User::factory()->adminPusat()->create(['email' => 'a@example.test']);
    $b = User::factory()->adminPusat()->create(['email' => 'b@example.test']);

    foreach (range(1, 5) as $i) {
        attempt($a->email);
    }

    attempt($b->email, 'password');
    $this->assertAuthenticatedAs($b);
});

test('S-04 the same email is blocked per IP pair only, and a different IP is not affected by the pair counter', function () {
    User::factory()->adminPusat()->create(['email' => 'ap@example.test']);

    // Failures from IP 10.0.0.1 only touch that pair's counter and the IP counter.
    foreach (range(1, 5) as $i) {
        LoginThrottle::recordFailure('hantu@example.test', '10.0.0.1');
    }

    expect(LoginThrottle::secondsBlocked('hantu@example.test', '10.0.0.1'))->toBeGreaterThan(0)
        ->and(LoginThrottle::secondsBlocked('hantu@example.test', '10.0.0.2'))->toBe(0)
        ->and(LoginThrottle::secondsBlocked('ap@example.test', '10.0.0.1'))->toBe(0);
});

test('S-04 mass guessing from one IP is stopped after the per-IP ceiling', function () {
    foreach (range(1, LoginThrottle::MAX_PER_IP) as $i) {
        LoginThrottle::recordFailure("acak{$i}@example.test", '10.9.9.9');
    }

    expect(LoginThrottle::secondsBlocked('baru@example.test', '10.9.9.9'))->toBeGreaterThan(0)
        ->and(LoginThrottle::secondsBlocked('baru@example.test', '10.9.9.8'))->toBe(0);
});

test('TD-09 the mentor panel login uses the same policy', function () {
    Filament::setCurrentPanel('mentor');
    $user = User::factory()->mentor()->create(['email' => 'm@example.test']);

    foreach (range(1, 5) as $i) {
        attempt($user->email);
    }

    expect($user->fresh()->dikunci_sampai)->not->toBeNull();
});

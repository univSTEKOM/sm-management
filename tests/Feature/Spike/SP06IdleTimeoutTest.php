<?php

/*
 * SP-06 / Implementation Plan 4.10: server-side idle timeout.
 * Every test checks the activity timestamp stored in the server session, not
 * only the response status. Requests go through the real Livewire endpoint.
 */

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Master\Models\Cabang;
use App\Filament\Admin\Resources\Master\Cabangs\Pages\ManageCabangs;
use App\Filament\Admin\Resources\Sekolahs\Pages\CreateSekolah;
use App\Filament\Admin\Resources\Sekolahs\Pages\ListSekolahs;
use App\Filament\Mentor\Pages\KegiatanSaya;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Models\User;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Testing\TestResponse;
use Tests\Support\Fixtures;
use Tests\Support\LivewireHttp;

beforeEach(function () {
    config(['access.idle_timeout_minutes' => 60]);
    $this->freezeSecond();
});

/** Page load that establishes the session and returns its HTML. */
function idleOpenPage(object $test, User $user, string $url): TestResponse
{
    $response = $test->actingAs($user)->get($url);
    $response->assertOk();
    $test->sessionCookieValue = LivewireHttp::sessionCookie($response);

    return $response;
}

function idleLivewire(object $test, array $components): TestResponse
{
    LivewireHttp::freshRequestState();

    $response = $test
        ->withCookie(config('session.cookie'), $test->sessionCookieValue)
        ->withHeaders(['X-Livewire' => '1', 'Accept' => 'application/json'])
        ->postJson(LivewireHttp::updateUri(), ['components' => $components]);

    $test->sessionCookieValue = LivewireHttp::sessionCookie($response) ?? $test->sessionCookieValue;

    return $response;
}

function idleStamp(): ?int
{
    $value = app('session.store')->get(EnforceIdleTimeout::SESSION_KEY);

    return $value === null ? null : (int) $value;
}

function idlePoll(object $test, string $snapshot): TestResponse
{
    return idleLivewire($test, [LivewireHttp::component($snapshot, [], [LivewireHttp::pollCall()])]);
}

dataset('panels', [
    'admin' => ['admin_pusat', '/admin'],
    'mentor' => ['mentor', '/mentor/kegiatan-saya'],
]);

function idleUser(string $role): User
{
    return match ($role) {
        'mentor' => User::factory()->mentor()->create(),
        default => User::factory()->adminPusat()->create(),
    };
}

test('IT-01/IT-07 pure polling past the limit ends the session on the server', function (string $role, string $url) {
    $user = idleUser($role);
    $page = idleOpenPage($this, $user, $url);
    $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);
    $loginStamp = idleStamp();

    expect($loginStamp)->not->toBeNull();

    for ($minute = 1; $minute <= 60; $minute++) {
        $this->travel(1)->minutes();
        idlePoll($this, $snapshot)->assertOk();
    }

    expect(idleStamp())->toBe($loginStamp);

    $this->travel(61)->seconds();
    $expired = idlePoll($this, $snapshot);

    expect($expired->status())->toBeIn([401, 302]);
    $this->assertGuest();
    expect(idleStamp())->toBeNull();
})->with('panels');

test('IT-02 activity before the limit keeps the session alive', function () {
    $user = User::factory()->adminPusat()->create();
    $page = idleOpenPage($this, $user, '/admin');
    $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);

    $this->travel(59)->minutes();
    $this->withCookie(config('session.cookie'), $this->sessionCookieValue)->get('/admin')->assertOk();
    $activityStamp = idleStamp();

    expect($activityStamp)->toBe(now()->getTimestamp());

    $this->travel(30)->minutes();
    idlePoll($this, $snapshot)->assertOk();
    $this->assertAuthenticatedAs($user);
});

test('IT-03 polling does not change the activity timestamp', function () {
    $user = User::factory()->mentor()->create();
    $page = idleOpenPage($this, $user, '/mentor/kegiatan-saya');
    $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);
    $before = idleStamp();

    $this->travel(10)->minutes();
    idlePoll($this, $snapshot)->assertOk();

    expect(idleStamp())->toBe($before);
});

test('IT-04 a request mixing polling with a user call counts as activity', function () {
    $user = User::factory()->adminPusat()->create();
    $page = idleOpenPage($this, $user, '/admin');
    $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);

    $this->travel(10)->minutes();
    idleLivewire($this, [LivewireHttp::component($snapshot, [], [
        LivewireHttp::pollCall(),
        LivewireHttp::call('markAllNotificationsAsRead'),
    ])])->assertOk();

    expect(idleStamp())->toBe(now()->getTimestamp());
});

test('IT-05 a refresh from a component outside the passive list counts as activity', function () {
    $user = User::factory()->mentor()->create();
    $page = idleOpenPage($this, $user, '/mentor/kegiatan-saya');
    $snapshot = LivewireHttp::snapshot($page->getContent(), KegiatanSaya::class);

    $this->travel(10)->minutes();
    idleLivewire($this, [LivewireHttp::component($snapshot, [], [LivewireHttp::pollCall()])])->assertOk();

    expect(idleStamp())->toBe(now()->getTimestamp());
});

test('IT-06 idle logout is audited', function () {
    $user = User::factory()->mentor()->create();
    $page = idleOpenPage($this, $user, '/mentor/kegiatan-saya');
    $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);

    $this->travel(61)->minutes();
    idlePoll($this, $snapshot);

    expect(AuditLog::where('aksi', 'logout_idle')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('IT-08 submitting a form through Livewire counts as activity', function () {
    $user = User::factory()->adminPusat()->create();
    Cabang::factory()->create();
    $page = idleOpenPage($this, $user, '/admin/sekolah/create');
    $snapshot = LivewireHttp::snapshot($page->getContent(), CreateSekolah::class);

    $this->travel(20)->minutes();
    // Validation fails (empty form) but the request is still a user submission.
    idleLivewire($this, [LivewireHttp::component($snapshot, [], [LivewireHttp::call('create')])]);

    expect(idleStamp())->toBe(now()->getTimestamp());
});

test('IT-09 mounting an action counts as activity', function () {
    $mentor = User::factory()->mentor()->create();
    $sekolah = Fixtures::sekolah(Cabang::factory()->create(), $mentor);
    $kegiatan = Fixtures::kegiatan($sekolah, $mentor);
    $page = idleOpenPage($this, $mentor, '/mentor/kegiatan-saya');
    $snapshot = LivewireHttp::snapshot($page->getContent(), KegiatanSaya::class);

    $this->travel(20)->minutes();
    idleLivewire($this, [LivewireHttp::component($snapshot, [], [
        LivewireHttp::call('mountAction', ['report', ['kegiatan' => $kegiatan->id]]),
    ])])->assertOk();

    expect(idleStamp())->toBe(now()->getTimestamp());
});

test('IT-10 mentor reschedule submission counts as activity', function () {
    $mentor = User::factory()->mentor()->create();
    $sekolah = Fixtures::sekolah(Cabang::factory()->create(), $mentor);
    $kegiatan = Fixtures::kegiatan($sekolah, $mentor);
    $page = idleOpenPage($this, $mentor, '/mentor/kegiatan-saya');
    $snapshot = LivewireHttp::snapshot($page->getContent(), KegiatanSaya::class);

    $this->travel(20)->minutes();
    $mounted = idleLivewire($this, [LivewireHttp::component($snapshot, [], [
        LivewireHttp::call('mountAction', ['reschedule', ['kegiatan' => $kegiatan->id]]),
    ])])->assertOk();

    $this->travel(5)->minutes();
    idleLivewire($this, [LivewireHttp::component($mounted->json('components.0.snapshot'), [
        'mountedActions.0.data.tanggal' => now()->addDays(10)->toDateString(),
        'mountedActions.0.data.alasan' => 'Sekolah meminta perubahan',
    ], [LivewireHttp::call('callMountedAction')])])->assertOk();

    expect(idleStamp())->toBe(now()->getTimestamp())
        ->and($kegiatan->fresh()->tanggal->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and($kegiatan->riwayatJadwal()->count())->toBe(1);
});

test('IT-11 table interaction (search update) counts as activity', function () {
    $user = User::factory()->adminPusat()->create();
    $page = idleOpenPage($this, $user, '/admin/sekolah');
    $snapshot = LivewireHttp::snapshot($page->getContent(), ListSekolahs::class);

    $this->travel(20)->minutes();
    idleLivewire($this, [LivewireHttp::component($snapshot, ['tableSearch' => 'sman'])])->assertOk();

    expect(idleStamp())->toBe(now()->getTimestamp());
});

test('IT-12 notification actions other than polling count as activity', function () {
    $user = User::factory()->mentor()->create();
    $page = idleOpenPage($this, $user, '/mentor/kegiatan-saya');
    $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);

    $this->travel(20)->minutes();
    idleLivewire($this, [LivewireHttp::component($snapshot, [], [LivewireHttp::call('markAllNotificationsAsRead')])])->assertOk();

    expect(idleStamp())->toBe(now()->getTimestamp());
});

test('IT-13 repeated pure polling never refreshes the timestamp', function () {
    $user = User::factory()->adminPusat()->create();
    $page = idleOpenPage($this, $user, '/admin');
    $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);
    $start = idleStamp();

    foreach (range(1, 5) as $i) {
        $this->travel(5)->minutes();
        idlePoll($this, $snapshot)->assertOk();
        expect(idleStamp())->toBe($start);
    }
});

/*
 * Second-panel counterparts (decision 23 Nov 2026, point 5): every IT scenario above that
 * ran on one panel is repeated on the other panel. IT-01/IT-07 already cover both.
 */
describe('second panel', function () {
    test('IT-02 (mentor) activity before the limit keeps the session alive', function () {
        $user = User::factory()->mentor()->create();
        $page = idleOpenPage($this, $user, '/mentor/kegiatan-saya');
        $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);

        $this->travel(59)->minutes();
        $this->withCookie(config('session.cookie'), $this->sessionCookieValue)->get('/mentor/kegiatan-saya')->assertOk();
        expect(idleStamp())->toBe(now()->getTimestamp());

        $this->travel(30)->minutes();
        idlePoll($this, $snapshot)->assertOk();
        $this->assertAuthenticatedAs($user);
    });

    test('IT-03 (admin) polling does not change the activity timestamp', function () {
        $page = idleOpenPage($this, User::factory()->adminPusat()->create(), '/admin');
        $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);
        $before = idleStamp();

        $this->travel(10)->minutes();
        idlePoll($this, $snapshot)->assertOk();

        expect(idleStamp())->toBe($before);
    });

    test('IT-04 (mentor) a request mixing polling with a user call counts as activity', function () {
        $page = idleOpenPage($this, User::factory()->mentor()->create(), '/mentor/kegiatan-saya');
        $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);

        $this->travel(10)->minutes();
        idleLivewire($this, [LivewireHttp::component($snapshot, [], [
            LivewireHttp::pollCall(),
            LivewireHttp::call('markAllNotificationsAsRead'),
        ])])->assertOk();

        expect(idleStamp())->toBe(now()->getTimestamp());
    });

    test('IT-05 (admin) a refresh from a component outside the passive list counts as activity', function () {
        $page = idleOpenPage($this, User::factory()->adminPusat()->create(), '/admin/sekolah');
        $snapshot = LivewireHttp::snapshot($page->getContent(), ListSekolahs::class);

        $this->travel(10)->minutes();
        idleLivewire($this, [LivewireHttp::component($snapshot, [], [LivewireHttp::pollCall()])])->assertOk();

        expect(idleStamp())->toBe(now()->getTimestamp());
    });

    test('IT-06 (admin) idle logout is audited', function () {
        $user = User::factory()->adminPusat()->create();
        $page = idleOpenPage($this, $user, '/admin');
        $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);

        $this->travel(61)->minutes();
        idlePoll($this, $snapshot);

        expect(AuditLog::where('aksi', 'logout_idle')->where('user_id', $user->id)->exists())->toBeTrue();
        $this->assertGuest();
    });

    test('IT-08 (mentor) submitting a modal form counts as activity', function () {
        $mentor = User::factory()->mentor()->create();
        $kegiatan = Fixtures::kegiatan(Fixtures::sekolah(Cabang::factory()->create(), $mentor), $mentor);
        $page = idleOpenPage($this, $mentor, '/mentor/kegiatan-saya');
        $snapshot = LivewireHttp::snapshot($page->getContent(), KegiatanSaya::class);

        $mounted = idleLivewire($this, [LivewireHttp::component($snapshot, [], [
            LivewireHttp::call('mountAction', ['report', ['kegiatan' => $kegiatan->id]]),
        ])])->assertOk();

        $this->travel(20)->minutes();
        // Validation fails (no status, no note) but the request is still a user submission.
        idleLivewire($this, [LivewireHttp::component($mounted->json('components.0.snapshot'), [], [
            LivewireHttp::call('callMountedAction'),
        ])]);

        expect(idleStamp())->toBe(now()->getTimestamp());
    });

    test('IT-09 (admin) mounting an action counts as activity', function () {
        $page = idleOpenPage($this, User::factory()->superadmin()->create(), '/admin/master/cabang');
        $snapshot = LivewireHttp::snapshot($page->getContent(), ManageCabangs::class);

        $this->travel(20)->minutes();
        idleLivewire($this, [LivewireHttp::component($snapshot, [], [
            LivewireHttp::call('mountAction', ['create']),
        ])])->assertOk();

        expect(idleStamp())->toBe(now()->getTimestamp());
    });

    test('IT-10 (admin) submitting a modal action counts as activity and saves', function () {
        $page = idleOpenPage($this, User::factory()->superadmin()->create(), '/admin/master/cabang');
        $snapshot = LivewireHttp::snapshot($page->getContent(), ManageCabangs::class);

        $this->travel(20)->minutes();
        $mounted = idleLivewire($this, [LivewireHttp::component($snapshot, [], [
            LivewireHttp::call('mountAction', ['create']),
        ])])->assertOk();

        $this->travel(5)->minutes();
        idleLivewire($this, [LivewireHttp::component($mounted->json('components.0.snapshot'), [
            'mountedActions.0.data.kode' => 'KDS',
            'mountedActions.0.data.nama' => 'Cabang Kudus',
        ], [LivewireHttp::call('callMountedAction')])])->assertOk();

        expect(idleStamp())->toBe(now()->getTimestamp())
            ->and(Cabang::where('kode', 'KDS')->exists())->toBeTrue();
    });

    test('IT-11 (mentor) typing into a modal field (property update) counts as activity', function () {
        $mentor = User::factory()->mentor()->create();
        $kegiatan = Fixtures::kegiatan(Fixtures::sekolah(Cabang::factory()->create(), $mentor), $mentor);
        $page = idleOpenPage($this, $mentor, '/mentor/kegiatan-saya');
        $snapshot = LivewireHttp::snapshot($page->getContent(), KegiatanSaya::class);

        $mounted = idleLivewire($this, [LivewireHttp::component($snapshot, [], [
            LivewireHttp::call('mountAction', ['report', ['kegiatan' => $kegiatan->id]]),
        ])])->assertOk();

        $this->travel(20)->minutes();
        idleLivewire($this, [LivewireHttp::component($mounted->json('components.0.snapshot'), [
            'mountedActions.0.data.catatan' => 'Sedang mengetik',
        ])])->assertOk();

        expect(idleStamp())->toBe(now()->getTimestamp());
    });

    test('IT-12 (admin) notification actions other than polling count as activity', function () {
        $page = idleOpenPage($this, User::factory()->adminPusat()->create(), '/admin');
        $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);

        $this->travel(20)->minutes();
        idleLivewire($this, [LivewireHttp::component($snapshot, [], [LivewireHttp::call('markAllNotificationsAsRead')])])->assertOk();

        expect(idleStamp())->toBe(now()->getTimestamp());
    });

    test('IT-13 (mentor) repeated pure polling never refreshes the timestamp', function () {
        $page = idleOpenPage($this, User::factory()->mentor()->create(), '/mentor/kegiatan-saya');
        $snapshot = LivewireHttp::snapshot($page->getContent(), DatabaseNotifications::class);
        $start = idleStamp();

        foreach (range(1, 5) as $i) {
            $this->travel(5)->minutes();
            idlePoll($this, $snapshot)->assertOk();
            expect(idleStamp())->toBe($start);
        }
    });
});

<?php

/*
 * Guards the static permission matrix (TD-08) against drift from PRD v1.3
 * section 9. The expected table below is written independently of
 * AccessConfig; any change to either side makes this test fail.
 *
 * Legend: A = all, C = own branch, T = assigned mentor, PJ = responsible
 * mentor, K = coordination read, - = denied.
 */

use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Models\Cabang;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Models\User;
use App\Support\Authorization\AccessConfig;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Fixtures;

const PRD_MATRIX = [
    //                                SA   AP   AC   M
    'sekolah.view' => ['A', 'A', 'C', 'A'],  // M: A-01 (asumsi), read only
    'sekolah.create' => ['A', 'A', 'C', '-'],
    'sekolah.update' => ['A', 'A', 'C', '-'],
    'sekolah.correct_cabang' => ['A', '-', '-', '-'],
    'sekolah.transfer' => ['-', '-', '-', '-'],
    'sekolah.archive' => ['A', 'A', 'C', '-'],
    // Not in PRD v1.3: owner decision 26 Nov 2026 (PHASE_LOG D-03 updated), Superadmin and Admin Pusat.
    'sekolah.mou' => ['A', 'A', '-', '-'],
    'sekolah.kategori' => ['A', 'A', 'C', '-'],
    'narahubung.manage' => ['A', 'A', 'C', '-'],
    'narahubung.propose' => ['-', '-', '-', 'T'],
    'narahubung.decide' => ['-', 'A', 'C', '-'],
    'penugasan.assign' => ['A', 'A', '-', '-'],  // AP: A-02 (asumsi)
    'kegiatan.assign_pelaksana' => ['A', 'A', '-', '-'],
    'kontak_awal.record' => ['-', '-', '-', 'T'],
    'kegiatan.view' => ['A', 'A', 'C', 'K'],
    'awal.create' => ['A', 'A', 'C', '-'],
    'tl.create' => ['A', 'A', 'C', 'PJ'], // C-01 conservative
    'tl.create_after_last' => ['-', 'A', '-', '-'],
    'kegiatan.reschedule' => ['A', 'A', 'C', 'T'],
    'kegiatan.delete' => ['A', 'A', 'C', '-'],
    'kegiatan.report' => ['-', '-', '-', 'T'],
    'kegiatan.admin_status' => ['-', 'A', '-', '-'],
    'laporan.view_detail' => ['A', 'A', 'C', 'T'],
    'rangkaian.set_last' => ['-', 'A', '-', '-'],
    'rangkaian.reopen' => ['-', 'A', '-', '-'],
    'sertifikat.view_status' => ['A', 'A', 'C', 'A'],
    'sertifikat.open_link' => ['A', 'A', 'C', 'T'],
    'sertifikat.manage_link' => ['-', 'A', '-', '-'],
    'dashboard.view' => ['A', 'A', 'C', 'T'],
    'dashboard.branch_compare' => ['A', 'A', 'A', '-'],
    'klasemen.view' => ['A', 'A', 'A', 'A'],
    'users.manage' => ['A', '-', '-', '-'],
    'roles.assign' => ['A', '-', '-', '-'],
    'master.manage' => ['A', '-', '-', '-'],
    'audit.view' => ['A', '-', '-', '-'],
];

const SCOPE_CODES = [
    AccessConfig::ALL => 'A',
    AccessConfig::CABANG => 'C',
    AccessConfig::ASSIGNED => 'T',
    AccessConfig::RESPONSIBLE => 'PJ',
    AccessConfig::COORDINATION => 'K',
    AccessConfig::NONE => '-',
];

test('AccessConfig covers exactly the PRD abilities', function () {
    expect(AccessConfig::abilities())->toEqualCanonicalizing(array_keys(PRD_MATRIX));
});

test('every role scope matches PRD section 9', function (string $ability) {
    config(['access.mentor_can_view_all_schools' => true]);
    $roles = [Role::Superadmin, Role::AdminPusat, Role::AdminCabang, Role::Mentor];

    foreach ($roles as $i => $role) {
        expect(SCOPE_CODES[AccessConfig::scope($ability, $role)])
            ->toBe(PRD_MATRIX[$ability][$i], "{$ability} for {$role->value}");
    }
})->with(array_keys(PRD_MATRIX));

test('A-01 switched off restricts mentor reads to assigned schools', function () {
    config(['access.mentor_can_view_all_schools' => false]);

    expect(AccessConfig::scope('sekolah.view', Role::Mentor))->toBe(AccessConfig::ASSIGNED)
        ->and(AccessConfig::scope('sertifikat.view_status', Role::Mentor))->toBe(AccessConfig::ASSIGNED);
});

describe('policies enforce role, branch and assignment', function () {
    beforeEach(function () {
        $this->cabangA = Cabang::factory()->create();
        $this->cabangB = Cabang::factory()->create();
        $this->mentorPj = User::factory()->mentor()->create();
        $this->mentorPelaksana = User::factory()->mentor()->create();
        $this->mentorLain = User::factory()->mentor()->create();
        $this->sekolah = Fixtures::sekolah($this->cabangA, $this->mentorPj);
        $this->kegiatan = Fixtures::kegiatan($this->sekolah, $this->mentorPelaksana);

        $this->users = [
            'superadmin' => User::factory()->superadmin()->create(),
            'admin_pusat' => User::factory()->adminPusat()->create(),
            'admin_cabang_sendiri' => User::factory()->adminCabang($this->cabangA)->create(),
            'admin_cabang_lain' => User::factory()->adminCabang($this->cabangB)->create(),
            'mentor_pj' => $this->mentorPj,
            'mentor_pelaksana' => $this->mentorPelaksana,
            'mentor_lain' => $this->mentorLain,
            'nonaktif_pusat' => User::factory()->adminPusat()->nonaktif()->create(),
        ];
    });

    test('kegiatan abilities', function (string $ability, array $allowed) {
        foreach ($this->users as $name => $user) {
            expect(Gate::forUser($user)->allows($ability, $this->kegiatan))
                ->toBe(in_array($name, $allowed, true), "{$ability} for {$name}");
        }
    })->with([
        'reschedule' => ['reschedule', ['superadmin', 'admin_pusat', 'admin_cabang_sendiri', 'mentor_pj', 'mentor_pelaksana']],
        'report' => ['report', ['mentor_pj', 'mentor_pelaksana']],
        'adminStatus' => ['adminStatus', ['admin_pusat']],
        'viewDetail' => ['viewDetail', ['superadmin', 'admin_pusat', 'admin_cabang_sendiri', 'mentor_pj', 'mentor_pelaksana']],
        'delete (never via generic delete)' => ['delete', []],
    ]);

    test('sekolah abilities', function (string $ability, array $allowed) {
        config(['access.mentor_can_view_all_schools' => true]);

        foreach ($this->users as $name => $user) {
            expect(Gate::forUser($user)->allows($ability, $this->sekolah))
                ->toBe(in_array($name, $allowed, true), "{$ability} for {$name}");
        }
    })->with([
        'view' => ['view', ['superadmin', 'admin_pusat', 'admin_cabang_sendiri', 'mentor_pj', 'mentor_pelaksana', 'mentor_lain']],
        'update' => ['update', ['superadmin', 'admin_pusat', 'admin_cabang_sendiri']],
        'archive' => ['archive', ['superadmin', 'admin_pusat', 'admin_cabang_sendiri']],
        'recordMou' => ['recordMou', ['superadmin', 'admin_pusat']],
        'viewMou' => ['viewMou', ['superadmin', 'admin_pusat']],
        'correctCabang (school has activities)' => ['correctCabang', []],
        'delete' => ['delete', []],
    ]);

    test('users.manage / roles.assign: only an active superadmin manages other accounts', function (string $ability) {
        $target = User::factory()->mentor()->create();

        foreach ($this->users as $name => $user) {
            expect(Gate::forUser($user)->allows($ability, $target))
                ->toBe($name === 'superadmin', "{$ability} for {$name}");
        }
    })->with(['view', 'update', 'assignRole', 'deactivate', 'resetPassword']);

    test('accounts are never deleted through the generic delete ability', function () {
        $target = User::factory()->mentor()->create();

        foreach ($this->users as $name => $user) {
            expect(Gate::forUser($user)->allows('delete', $target))->toBeFalse("delete for {$name}");
        }
    });

    test('master.manage: only an active superadmin manages master data and settings', function () {
        $cabang = Cabang::factory()->create();

        foreach ($this->users as $name => $user) {
            $expected = $name === 'superadmin';
            expect(Gate::forUser($user)->allows('viewAny', Cabang::class))->toBe($expected, "viewAny for {$name}")
                ->and(Gate::forUser($user)->allows('update', $cabang))->toBe($expected, "update for {$name}")
                ->and(Gate::forUser($user)->allows('manageSettings', AppSetting::class))->toBe($expected, "settings for {$name}");
        }
    });

    test('superadmin may correct the branch only while the school has no activity', function () {
        $kosong = Fixtures::sekolah($this->cabangA);

        expect(Gate::forUser($this->users['superadmin'])->allows('correctCabang', $kosong))->toBeTrue()
            ->and(Gate::forUser($this->users['admin_pusat'])->allows('correctCabang', $kosong))->toBeFalse();
    });

    test('closed activities and archived schools refuse writes', function () {
        $this->kegiatan->update(['status' => StatusKegiatan::Dibatalkan]);
        expect(Gate::forUser($this->mentorPelaksana)->allows('reschedule', $this->kegiatan->fresh()))->toBeFalse();

        $this->sekolah->update(['diarsipkan_at' => now(), 'alasan_arsip' => 'Mundur']);
        $open = Fixtures::kegiatan($this->sekolah->fresh(), $this->mentorPelaksana);
        expect(Gate::forUser($this->mentorPelaksana)->allows('report', $open))->toBeFalse()
            ->and(Gate::forUser($this->users['admin_pusat'])->allows('update', $this->sekolah->fresh()))->toBeFalse();
    });
});

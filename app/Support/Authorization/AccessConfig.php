<?php

namespace App\Support\Authorization;

use App\Domain\Shared\Enums\Role;

/**
 * Single source of the role permission matrix (Implementation Plan section 6,
 * PRD section 9). Permissions are static by decision TD-08: change them only
 * through reviewed code changes, never at runtime.
 *
 * Scope values:
 *  - all:         every record
 *  - cabang:      records of the user's own branch (Admin Cabang)
 *  - assigned:    schools/activities where the mentor is the responsible
 *                 mentor or the executing mentor of that activity
 *  - responsible: schools where the mentor is the active responsible mentor
 *  - coordination: read-only coordination data (PRD R-01)
 *  - none:        denied
 */
final class AccessConfig
{
    public const ALL = 'all';

    public const CABANG = 'cabang';

    public const ASSIGNED = 'assigned';

    public const RESPONSIBLE = 'responsible';

    public const COORDINATION = 'coordination';

    public const NONE = 'none';

    /** @var array<string, array<string, string>> ability => [role => scope] */
    private const MATRIX = [
        // Sekolah & mentor
        'sekolah.view' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::ASSIGNED],
        'sekolah.create' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::NONE],
        'sekolah.update' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::NONE],
        'sekolah.correct_cabang' => ['superadmin' => self::ALL, 'admin_pusat' => self::NONE, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'sekolah.transfer' => ['superadmin' => self::NONE, 'admin_pusat' => self::NONE, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'sekolah.archive' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::NONE],
        'sekolah.kategori' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::NONE],
        'narahubung.manage' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::NONE],
        'narahubung.propose' => ['superadmin' => self::NONE, 'admin_pusat' => self::NONE, 'admin_cabang' => self::NONE, 'mentor' => self::ASSIGNED],
        'narahubung.decide' => ['superadmin' => self::NONE, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::NONE],
        // PRD 27.3 A-02 (asumsi): responsible mentor assignment by Admin Pusat.
        'penugasan.assign' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'kegiatan.assign_pelaksana' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'kontak_awal.record' => ['superadmin' => self::NONE, 'admin_pusat' => self::NONE, 'admin_cabang' => self::NONE, 'mentor' => self::ASSIGNED],

        // Jadwal, rangkaian, laporan
        'kegiatan.view' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::COORDINATION],
        'awal.create' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::NONE],
        // C-01 (conservative): only the responsible mentor may add sessions (PD-04, BR-ACT-12).
        'tl.create' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::RESPONSIBLE],
        'tl.create_after_last' => ['superadmin' => self::NONE, 'admin_pusat' => self::ALL, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'kegiatan.reschedule' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::ASSIGNED],
        'kegiatan.delete' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::NONE],
        'kegiatan.report' => ['superadmin' => self::NONE, 'admin_pusat' => self::NONE, 'admin_cabang' => self::NONE, 'mentor' => self::ASSIGNED],
        'kegiatan.admin_status' => ['superadmin' => self::NONE, 'admin_pusat' => self::ALL, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'laporan.view_detail' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::ASSIGNED],
        'rangkaian.set_last' => ['superadmin' => self::NONE, 'admin_pusat' => self::ALL, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'rangkaian.reopen' => ['superadmin' => self::NONE, 'admin_pusat' => self::ALL, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],

        // Sertifikat, insight, administrasi
        'sertifikat.view_status' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::ASSIGNED],
        'sertifikat.open_link' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::ASSIGNED],
        'sertifikat.manage_link' => ['superadmin' => self::NONE, 'admin_pusat' => self::ALL, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'dashboard.view' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::CABANG, 'mentor' => self::ASSIGNED],
        'dashboard.branch_compare' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::ALL, 'mentor' => self::NONE],
        'klasemen.view' => ['superadmin' => self::ALL, 'admin_pusat' => self::ALL, 'admin_cabang' => self::ALL, 'mentor' => self::ALL],
        'users.manage' => ['superadmin' => self::ALL, 'admin_pusat' => self::NONE, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'roles.assign' => ['superadmin' => self::ALL, 'admin_pusat' => self::NONE, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'master.manage' => ['superadmin' => self::ALL, 'admin_pusat' => self::NONE, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
        'audit.view' => ['superadmin' => self::ALL, 'admin_pusat' => self::NONE, 'admin_cabang' => self::NONE, 'mentor' => self::NONE],
    ];

    /**
     * PRD 27.3 A-01 (asumsi): mentors may read every school in Jawa Tengah.
     * Read-only; write abilities always require an assignment.
     */
    public static function mentorCanViewAllSchools(): bool
    {
        return (bool) config('access.mentor_can_view_all_schools', true);
    }

    public static function scope(string $ability, Role $role): string
    {
        $scope = self::MATRIX[$ability][$role->value] ?? self::NONE;

        if ($ability === 'sekolah.view' && $role === Role::Mentor && self::mentorCanViewAllSchools()) {
            return self::ALL;
        }

        if ($ability === 'sertifikat.view_status' && $role === Role::Mentor && self::mentorCanViewAllSchools()) {
            return self::ALL;
        }

        return $scope;
    }

    /** @return list<string> */
    public static function abilities(): array
    {
        return array_keys(self::MATRIX);
    }

    public static function allows(string $ability, Role $role): bool
    {
        return self::scope($ability, $role) !== self::NONE;
    }
}

<?php

namespace App\Policies;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Models\User;
use App\Support\Authorization\AccessConfig;
use App\Support\Authorization\ScopeResolver;

/** Abilities come from AccessConfig (static matrix, TD-08). Deny by default. */
class KegiatanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->aktif && AccessConfig::allows('kegiatan.view', $user->role);
    }

    public function view(User $user, Kegiatan $kegiatan): bool
    {
        return ScopeResolver::allows($user, 'kegiatan.view', null, $kegiatan);
    }

    /** Report notes, meeting link and certificate details (R-01, AC-RPT-02). */
    public function viewDetail(User $user, Kegiatan $kegiatan): bool
    {
        return ScopeResolver::allows($user, 'laporan.view_detail', null, $kegiatan);
    }

    /** BR-ACT-05/11: open activities of non-archived schools only. */
    public function reschedule(User $user, Kegiatan $kegiatan): bool
    {
        return $kegiatan->status->isOpen()
            && ! $kegiatan->sekolah->isArchived()
            && ScopeResolver::allows($user, 'kegiatan.reschedule', null, $kegiatan);
    }

    /** Mentor report (BR-ACT-04): assigned mentor only, school not archived. */
    public function report(User $user, Kegiatan $kegiatan): bool
    {
        return ! $kegiatan->sekolah->isArchived()
            && ScopeResolver::allows($user, 'kegiatan.report', null, $kegiatan);
    }

    /** PD-05: Admin Pusat only, with mandatory reason enforced in the action. */
    public function adminStatus(User $user, Kegiatan $kegiatan): bool
    {
        return ! $kegiatan->sekolah->isArchived()
            && ScopeResolver::allows($user, 'kegiatan.admin_status', null, $kegiatan);
    }

    public function delete(User $user, Kegiatan $kegiatan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

<?php

namespace App\Http\Controllers;

use App\Domain\Sekolah\Models\Sekolah;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MouDownloadController extends Controller
{
    /**
     * Download the MOU PDF document for a school.
     * Only Superadmin and Admin Pusat (who have sekolah.mou) are authorised.
     */
    public function download(Sekolah $sekolah): StreamedResponse
    {
        Gate::authorize('viewMou', $sekolah);

        abort_unless($sekolah->mou_dokumen_path && Storage::disk('local')->exists($sekolah->mou_dokumen_path), 404);

        return Storage::disk('local')->download(
            $sekolah->mou_dokumen_path,
            $sekolah->mou_dokumen_nama_asli ?? 'mou.pdf',
        );
    }
}

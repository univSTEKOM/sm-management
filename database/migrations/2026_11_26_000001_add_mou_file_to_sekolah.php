<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision 26 Nov 2026: replace URL-based MOU document link with an uploaded
 * PDF file stored on the local private disk. Superadmin is now also allowed to record MOU.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the URL check constraint added in 2026_11_23_000002
        DB::statement('ALTER TABLE sekolah DROP CONSTRAINT IF EXISTS sekolah_mou_url_check');

        Schema::table('sekolah', function (Blueprint $table) {
            // Rename the existing URL column to store a local file path instead
            $table->renameColumn('mou_dokumen_url', 'mou_dokumen_path');
            // Store the original filename for display
            $table->string('mou_dokumen_nama_asli', 255)->nullable()->after('mou_dokumen_path');
        });
    }

    public function down(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropColumn('mou_dokumen_nama_asli');
            $table->renameColumn('mou_dokumen_path', 'mou_dokumen_url');
        });

        DB::statement("ALTER TABLE sekolah ADD CONSTRAINT sekolah_mou_url_check CHECK (mou_dokumen_url IS NULL OR mou_dokumen_url ~* '^https?://')");
    }
};

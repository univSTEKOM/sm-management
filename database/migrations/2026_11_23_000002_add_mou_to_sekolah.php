<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision 23 Nov 2026 (PHASE_LOG D-03): a school is "Sudah MOU" once Admin Pusat
 * has recorded the MOU document. The document is stored as a link, like certificates
 * (assumption F3-A01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->text('mou_dokumen_url')->nullable()->after('tl_selesai_at');
            $table->date('mou_tanggal')->nullable()->after('mou_dokumen_url');
            $table->foreignId('mou_dicatat_oleh')->nullable()->after('mou_tanggal')->constrained('users')->restrictOnDelete();
            $table->timestampTz('mou_dicatat_at')->nullable()->after('mou_dicatat_oleh');
        });

        DB::statement("ALTER TABLE sekolah ADD CONSTRAINT sekolah_mou_url_check CHECK (mou_dokumen_url IS NULL OR mou_dokumen_url ~* '^https?://')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sekolah DROP CONSTRAINT IF EXISTS sekolah_mou_url_check');
        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mou_dicatat_oleh');
            $table->dropColumn(['mou_dokumen_url', 'mou_tanggal', 'mou_dicatat_at']);
        });
    }
};

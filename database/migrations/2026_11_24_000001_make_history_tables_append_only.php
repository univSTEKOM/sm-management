<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The history tables are append-only at database level, like audit_log (AGENTS.md, BR-ACT-06):
 * schedule versions, report revisions, series history, contact history and branch corrections
 * can be added but never changed or removed, not even by a direct SQL session.
 */
return new class extends Migration
{
    private const TABLES = [
        'kegiatan_jadwal_riwayat',
        'laporan_revisi',
        'rangkaian_tl_riwayat',
        'kontak_awal',
        'narahubung_riwayat',
        'sekolah_cabang_koreksi',
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION history_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION '% is append-only (history table)', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        foreach (self::TABLES as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_no_update_delete BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION history_append_only()");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update_delete ON {$table}");
        }

        DB::unprepared('DROP FUNCTION IF EXISTS history_append_only()');
    }
};

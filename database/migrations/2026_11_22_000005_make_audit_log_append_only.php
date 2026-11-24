<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BR-AUD-03: the audit trail is append-only at database level, so no
 * application path (model, query builder, raw SQL) can change or delete it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_log_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_log is append-only (BR-AUD-03)';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_log_no_update_delete
                BEFORE UPDATE OR DELETE ON audit_log
                FOR EACH ROW EXECUTE FUNCTION audit_log_append_only();

            CREATE TRIGGER audit_log_no_truncate
                BEFORE TRUNCATE ON audit_log
                FOR EACH STATEMENT EXECUTE FUNCTION audit_log_append_only();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_log_no_truncate ON audit_log;
            DROP TRIGGER IF EXISTS audit_log_no_update_delete ON audit_log;
            DROP FUNCTION IF EXISTS audit_log_append_only();
            SQL);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->string('dedup_key')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        // Scheduled reminders must not be sent twice (H-1 / H+1 jobs are idempotent).
        DB::statement('CREATE UNIQUE INDEX notifications_dedup_key_unique ON notifications (dedup_key) WHERE dedup_key IS NOT NULL');

        Schema::create('audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestampTz('occurred_at')->useCurrent();
            // Kept even when the user is deactivated (BR-AUD-02); users are never hard-deleted.
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('role_saat_aksi')->nullable();
            $table->string('aksi');
            $table->string('entitas')->nullable();
            $table->string('entitas_id')->nullable();
            $table->jsonb('sebelum')->nullable();
            $table->jsonb('sesudah')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->index(['entitas', 'entitas_id']);
            $table->index(['user_id', 'occurred_at']);
            $table->index('occurred_at');
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->jsonb('value');
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('notifications');
    }
};

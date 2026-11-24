<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nama_normal');
            $table->string('jenjang');
            $table->text('alamat')->nullable();
            $table->foreignId('kabupaten_kota_id')->constrained('kabupaten_kota')->restrictOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('posisi_tahap')->default('calon_mitra');
            $table->date('tanggal_mitra')->nullable();
            $table->timestampTz('tl_selesai_at')->nullable();
            $table->timestampTz('diarsipkan_at')->nullable();
            $table->text('alasan_arsip')->nullable();
            $table->foreignId('diarsipkan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            // BR-SCH-03/13: unique including archived schools.
            $table->unique(['nama_normal', 'jenjang', 'kabupaten_kota_id']);
            $table->index(['cabang_id', 'posisi_tahap']);
        });

        DB::statement("ALTER TABLE sekolah ADD CONSTRAINT sekolah_jenjang_check CHECK (jenjang IN ('SMA','SMK','MA'))");
        DB::statement("ALTER TABLE sekolah ADD CONSTRAINT sekolah_posisi_tahap_check CHECK (posisi_tahap IN ('calon_mitra','mitra_belum_tl','mitra_tl','selesai_program'))");
        DB::statement('ALTER TABLE sekolah ADD CONSTRAINT sekolah_arsip_alasan_check CHECK (diarsipkan_at IS NULL OR (alasan_arsip IS NOT NULL AND length(trim(alasan_arsip)) > 0))');
        DB::statement('CREATE INDEX sekolah_nama_normal_trgm ON sekolah USING gin (nama_normal gin_trgm_ops)');

        Schema::create('sekolah_kategori', function (Blueprint $table) {
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('kategori_id')->constrained('kategori_pelatihan')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['sekolah_id', 'kategori_id']);
            $table->index('kategori_id');
        });

        Schema::create('sekolah_cabang_koreksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('cabang_lama_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('cabang_baru_id')->constrained('cabang')->restrictOnDelete();
            $table->text('alasan');
            $table->foreignId('oleh')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('narahubung', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->string('nama');
            $table->string('jabatan')->nullable();
            $table->string('nomor_wa');
            $table->string('email')->nullable();
            $table->boolean('is_utama')->default(false);
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        // BR-SCH-06: exactly one active primary contact per school (at most one enforced here).
        DB::statement('CREATE UNIQUE INDEX narahubung_satu_utama ON narahubung (sekolah_id) WHERE is_utama AND aktif');

        Schema::create('narahubung_usulan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('narahubung_id')->nullable()->constrained('narahubung')->restrictOnDelete();
            $table->jsonb('data_sebelum')->nullable();
            $table->jsonb('data_usulan');
            $table->foreignId('diusulkan_oleh')->constrained('users')->restrictOnDelete();
            $table->timestampTz('diajukan_at')->useCurrent();
            $table->string('status')->default('diajukan');
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('diputuskan_at')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->index(['sekolah_id', 'status']);
        });

        DB::statement("ALTER TABLE narahubung_usulan ADD CONSTRAINT narahubung_usulan_status_check CHECK (status IN ('diajukan','disetujui','ditolak','perlu_ditinjau'))");

        Schema::create('narahubung_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('narahubung_id')->constrained('narahubung')->restrictOnDelete();
            $table->jsonb('data_sebelum')->nullable();
            $table->jsonb('data_sesudah');
            $table->foreignId('diubah_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('usulan_id')->nullable()->constrained('narahubung_usulan')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('penugasan_mentor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('mulai')->useCurrent();
            $table->timestampTz('selesai')->nullable();
            $table->foreignId('ditugaskan_oleh')->constrained('users')->restrictOnDelete();
            $table->string('alasan_selesai')->nullable();
            $table->timestamps();
        });

        // BR-MNT-05: one active responsible mentor per school.
        DB::statement('CREATE UNIQUE INDEX penugasan_satu_aktif ON penugasan_mentor (sekolah_id) WHERE selesai IS NULL');
        DB::statement('CREATE INDEX penugasan_mentor_aktif ON penugasan_mentor (mentor_id) WHERE selesai IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_mentor');
        Schema::dropIfExists('narahubung_riwayat');
        Schema::dropIfExists('narahubung_usulan');
        Schema::dropIfExists('narahubung');
        Schema::dropIfExists('sekolah_cabang_koreksi');
        Schema::dropIfExists('sekolah_kategori');
        Schema::dropIfExists('sekolah');
    }
};

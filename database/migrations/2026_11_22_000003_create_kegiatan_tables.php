<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->string('tahap');
            $table->unsignedInteger('urutan');
            $table->string('nama_sesi');
            $table->foreignId('jenis_kegiatan_id')->constrained('jenis_kegiatan')->restrictOnDelete();
            $table->foreignId('metode_id')->constrained('metode_kegiatan')->restrictOnDelete();
            $table->date('tanggal');
            $table->time('waktu_mulai');
            $table->time('waktu_selesai');
            $table->text('lokasi')->nullable();
            $table->text('tautan_pertemuan')->nullable();
            $table->foreignId('mentor_pelaksana_id')->constrained('users')->restrictOnDelete();
            $table->boolean('pelaksana_ditunjuk_khusus')->default(false);
            $table->text('keterangan')->nullable();
            $table->string('status')->default('belum_dimulai');
            $table->boolean('is_sesi_terakhir')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['sekolah_id', 'tahap', 'urutan']);
            $table->index(['tanggal', 'status']);
            $table->index(['mentor_pelaksana_id', 'tanggal']);
            $table->index(['sekolah_id', 'tahap']);
        });

        DB::statement("ALTER TABLE kegiatan ADD CONSTRAINT kegiatan_tahap_check CHECK (tahap IN ('awal','tindak_lanjut'))");
        DB::statement("ALTER TABLE kegiatan ADD CONSTRAINT kegiatan_status_check CHECK (status IN ('belum_dimulai','sedang_berlangsung','selesai','dibatalkan'))");
        DB::statement('ALTER TABLE kegiatan ADD CONSTRAINT kegiatan_waktu_check CHECK (waktu_selesai > waktu_mulai)');
        DB::statement("ALTER TABLE kegiatan ADD CONSTRAINT kegiatan_sesi_terakhir_tl_check CHECK (tahap = 'tindak_lanjut' OR is_sesi_terakhir = false)");
        // BR-AWL-03: at most one non-cancelled initial training per school.
        DB::statement("CREATE UNIQUE INDEX kegiatan_satu_awal_aktif ON kegiatan (sekolah_id) WHERE tahap = 'awal' AND status <> 'dibatalkan'");
        // BR-TL-07: at most one last-session marker per school.
        DB::statement('CREATE UNIQUE INDEX kegiatan_satu_sesi_terakhir ON kegiatan (sekolah_id) WHERE is_sesi_terakhir');

        Schema::create('kegiatan_jadwal_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->restrictOnDelete();
            $table->unsignedInteger('versi');
            $table->jsonb('sebelum');
            $table->jsonb('sesudah');
            $table->text('alasan');
            $table->foreignId('diubah_oleh')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['kegiatan_id', 'versi']);
        });

        Schema::create('rangkaian_tl_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->string('aksi');
            $table->foreignId('kegiatan_id')->nullable()->constrained('kegiatan')->restrictOnDelete();
            $table->text('alasan')->nullable();
            // NULL means the system performed the action.
            $table->foreignId('oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index('sekolah_id');
        });

        DB::statement("ALTER TABLE rangkaian_tl_riwayat ADD CONSTRAINT rangkaian_aksi_check CHECK (aksi IN ('tetapkan_sesi_terakhir','pindah_sesi_terakhir','selesai_otomatis','buka_kembali','sesi_terakhir_dibatalkan'))");

        Schema::create('laporan_revisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->restrictOnDelete();
            $table->unsignedInteger('versi');
            $table->string('status');
            $table->text('catatan');
            $table->foreignId('ditulis_oleh')->constrained('users')->restrictOnDelete();
            $table->string('peran_penulis');
            $table->text('alasan_perubahan_admin')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['kegiatan_id', 'versi']);
        });

        DB::statement("ALTER TABLE laporan_revisi ADD CONSTRAINT laporan_status_check CHECK (status IN ('belum_dimulai','sedang_berlangsung','selesai','dibatalkan'))");
        DB::statement("ALTER TABLE laporan_revisi ADD CONSTRAINT laporan_peran_check CHECK (peran_penulis IN ('mentor','admin_pusat'))");
        DB::statement("ALTER TABLE laporan_revisi ADD CONSTRAINT laporan_alasan_admin_check CHECK (peran_penulis <> 'admin_pusat' OR (alasan_perubahan_admin IS NOT NULL AND length(trim(alasan_perubahan_admin)) > 0))");
        DB::statement('ALTER TABLE laporan_revisi ADD CONSTRAINT laporan_catatan_check CHECK (length(trim(catatan)) > 0)');

        Schema::create('kontak_awal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('versi');
            $table->boolean('sudah_dihubungi');
            $table->date('tanggal_kontak')->nullable();
            $table->text('catatan')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['sekolah_id', 'versi']);
        });

        Schema::create('sertifikat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->string('tahap');
            $table->foreignId('kegiatan_acuan_id')->constrained('kegiatan')->restrictOnDelete();
            $table->text('link')->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->foreignId('diinput_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('diinput_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['sekolah_id', 'tahap']);
        });

        DB::statement("ALTER TABLE sertifikat ADD CONSTRAINT sertifikat_tahap_check CHECK (tahap IN ('awal','tindak_lanjut'))");
        DB::statement("ALTER TABLE sertifikat ADD CONSTRAINT sertifikat_link_check CHECK (link IS NULL OR link ~* '^https?://')");
    }

    public function down(): void
    {
        Schema::dropIfExists('sertifikat');
        Schema::dropIfExists('kontak_awal');
        Schema::dropIfExists('laporan_revisi');
        Schema::dropIfExists('rangkaian_tl_riwayat');
        Schema::dropIfExists('kegiatan_jadwal_riwayat');
        Schema::dropIfExists('kegiatan');
    }
};

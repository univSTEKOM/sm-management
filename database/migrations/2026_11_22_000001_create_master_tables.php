<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kabupaten_kota', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('cabang', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kategori_pelatihan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('jenis_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('metode_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->boolean('butuh_lokasi')->default(false);
            $table->boolean('butuh_tautan')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('superadmin','admin_pusat','admin_cabang','mentor'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_admin_cabang_has_cabang CHECK (role <> 'admin_cabang' OR cabang_id IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_admin_cabang_has_cabang');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
        });
        Schema::dropIfExists('metode_kegiatan');
        Schema::dropIfExists('jenis_kegiatan');
        Schema::dropIfExists('kategori_pelatihan');
        Schema::dropIfExists('cabang');
        Schema::dropIfExists('kabupaten_kota');
    }
};

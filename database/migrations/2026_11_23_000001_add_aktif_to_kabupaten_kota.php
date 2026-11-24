<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-MST-02: master data in use can only be deactivated, not deleted. PRD 16.10 lists no
 * status column for kabupaten/kota, so it is added to make deactivation possible
 * (assumption F2-A01 in docs/PHASE_LOG.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kabupaten_kota', function (Blueprint $table) {
            $table->boolean('aktif')->default(true)->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('kabupaten_kota', function (Blueprint $table) {
            $table->dropColumn('aktif');
        });
    }
};

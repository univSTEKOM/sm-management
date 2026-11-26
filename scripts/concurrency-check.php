<?php

/*
 * Multi-process check of the Fase 4 write paths against a REAL concurrent database (a throwaway
 * database, never the dev or production one). Pest runs inside one transaction, which cannot show
 * races, so this script commits real rows and fires parallel PHP processes at the actions.
 *
 *   docker compose exec -u 1000:1000 app php scripts/concurrency-check.php
 *
 * Worker mode (internal): php scripts/concurrency-check.php worker <scenario> <json>
 */

use App\Domain\Jadwal\Actions\CreateJadwalAwal;
use App\Domain\Jadwal\Actions\CreateSesiTindakLanjut;
use App\Domain\Jadwal\Actions\RescheduleKegiatan;
use App\Domain\Jadwal\Actions\UpdateKegiatanDetail;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$dbName = getenv('CONC_DB') ?: 'sm_concurrency_check';
putenv("DB_DATABASE={$dbName}");
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $dbName;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? '') === 'worker') {
    [$scenario, $payload] = [$argv[2], json_decode($argv[3], true)];
    // Barrier: all workers start their action at the same wall-clock moment.
    while (microtime(true) < $payload['start']) {
        usleep(200);
    }

    $actor = User::findOrFail($payload['actor']);
    $result = ['ok' => false, 'detail' => ''];

    try {
        match ($scenario) {
            'tl' => app(CreateSesiTindakLanjut::class)->handle($actor, Sekolah::findOrFail($payload['sekolah']), $payload['input']),
            'awal' => app(CreateJadwalAwal::class)->handle($actor, Sekolah::findOrFail($payload['sekolah']), $payload['input']),
            'detail' => app(UpdateKegiatanDetail::class)->handle($actor, Kegiatan::findOrFail($payload['kegiatan']), $payload['input'], $payload['version']),
            'reschedule' => app(RescheduleKegiatan::class)->handle($actor, Kegiatan::findOrFail($payload['kegiatan']), $payload['input'], 'Uji konkurensi', $payload['version']),
        };
        $result['ok'] = true;
    } catch (Throwable $e) {
        $result['detail'] = class_basename($e).': '.mb_substr($e->getMessage(), 0, 120);
    }

    echo json_encode($result);
    exit(0);
}

// ---- Orchestrator ----
$admin = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=postgres', env('DB_HOST'), env('DB_PORT')), env('DB_USERNAME'), env('DB_PASSWORD'));
$admin->exec("DROP DATABASE IF EXISTS {$dbName}");
$admin->exec("CREATE DATABASE {$dbName}");
DB::purge();
Artisan::call('migrate', ['--force' => true]);

$cabang = Cabang::factory()->create();
$ap = User::factory()->adminPusat()->create();
$mentor = User::factory()->mentor()->create();
$jenis = JenisKegiatan::create(['nama' => 'Pelatihan']);
$online = MetodeKegiatan::create(['nama' => 'Online', 'butuh_tautan' => true]);
MetodeKegiatan::create(['nama' => 'Offline', 'butuh_lokasi' => true]);
AppSetting::create(['key' => AppSetting::LOKASI_KAMPUS_PUSAT, 'value' => 'Kampus Pusat']);

$makeSekolah = function (PosisiTahap $posisi) use ($cabang, $mentor, $ap): Sekolah {
    $s = Sekolah::factory()->create(['cabang_id' => $cabang->id, 'posisi_tahap' => $posisi]);
    PenugasanMentor::create(['sekolah_id' => $s->id, 'mentor_id' => $mentor->id, 'ditugaskan_oleh' => $ap->id]);

    return $s;
};

$runParallel = function (string $scenario, array $payloads): array {
    $start = microtime(true) + 3.0; // time for every PHP process to boot
    $procs = [];

    foreach ($payloads as $i => $payload) {
        $cmd = [PHP_BINARY, __FILE__, 'worker', $scenario, json_encode($payload + ['start' => $start])];
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['CONC_DB' => getenv('DB_DATABASE')] + getenv());
        $procs[$i] = [$procs[$i], $pipes];
    }

    return array_map(function ($p) {
        $out = stream_get_contents($p[1][1]);
        proc_close($p[0]);

        return json_decode($out, true) ?? ['ok' => false, 'detail' => 'no output'];
    }, $procs);
};

$input = [
    'tanggal' => now()->addWeek()->toDateString(), 'waktu_mulai' => '09:00', 'waktu_selesai' => '11:00',
    'jenis_kegiatan_id' => $jenis->id, 'metode_id' => $online->id, 'tautan_pertemuan' => 'https://meet.example.test/x',
];
$fail = false;
$report = function (string $name, bool $pass, string $detail) use (&$fail) {
    $fail = $fail || ! $pass;
    echo ($pass ? 'PASS' : 'FAIL')."  {$name}  {$detail}\n";
};

// 1. Eight simultaneous session creations: eight distinct, gapless numbers, no error.
$sekolah = $makeSekolah(PosisiTahap::MitraBelumTl);
Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'tahap' => Tahap::Awal, 'urutan' => 1, 'status' => StatusKegiatan::Selesai, 'mentor_pelaksana_id' => $mentor->id, 'created_by' => $ap->id]);
$res = $runParallel('tl', array_fill(0, 8, ['actor' => $ap->id, 'sekolah' => $sekolah->id, 'input' => $input]));
$numbers = Kegiatan::where('sekolah_id', $sekolah->id)->where('tahap', 'tindak_lanjut')->orderBy('urutan')->pluck('urutan')->all();
$errors = collect($res)->where('ok', false)->pluck('detail')->unique()->implode(' | ');
$report('8 parallel sessions get unique gapless numbers', $numbers === range(1, 8) && collect($res)->every('ok'), 'numbers='.implode(',', $numbers).($errors ? " errors={$errors}" : ''));

// 2. Six simultaneous initial-training requests: exactly one wins, the rest get the PRD message.
$sekolah2 = $makeSekolah(PosisiTahap::CalonMitra);
$res = $runParallel('awal', array_fill(0, 6, ['actor' => $ap->id, 'sekolah' => $sekolah2->id, 'input' => ['tanggal' => now()->subDay()->toDateString(), 'waktu_mulai' => '09:00', 'waktu_selesai' => '12:00', 'jenis_kegiatan_id' => $jenis->id]]));
$wins = collect($res)->where('ok', true)->count();
$count = Kegiatan::where('sekolah_id', $sekolah2->id)->where('tahap', 'awal')->where('status', '<>', 'dibatalkan')->count();
$others = collect($res)->where('ok', false)->pluck('detail')->unique()->implode(' | ');
$report('6 parallel initial trainings: exactly one', $wins === 1 && $count === 1, "winners={$wins} rows={$count} others=[{$others}]");

// 3. Six detail saves from the same loaded version: one wins, five are told to reload, no overwrite.
$kegiatan = Kegiatan::where('sekolah_id', $sekolah->id)->where('urutan', 1)->where('tahap', 'tindak_lanjut')->firstOrFail();
$res = $runParallel('detail', array_map(fn ($i) => ['actor' => $ap->id, 'kegiatan' => $kegiatan->id, 'version' => $kegiatan->version, 'input' => ['keterangan' => "penulis {$i}"]], range(1, 6)));
$wins = collect($res)->where('ok', true)->count();
$fresh = $kegiatan->fresh();
$stale = collect($res)->where('ok', false)->every(fn ($r) => str_contains($r['detail'], 'diubah pengguna lain'));
$report('6 parallel saves of one version: one wins', $wins === 1 && $stale && $fresh->version === $kegiatan->version + 1, "winners={$wins} version={$fresh->version} keterangan={$fresh->keterangan}");

// 4. Six parallel reschedules from the same version: one wins, exactly one history row.
$kegiatan = Kegiatan::where('sekolah_id', $sekolah->id)->where('urutan', 2)->where('tahap', 'tindak_lanjut')->firstOrFail();
$res = $runParallel('reschedule', array_map(fn ($i) => ['actor' => $ap->id, 'kegiatan' => $kegiatan->id, 'version' => $kegiatan->version, 'input' => ['tanggal' => now()->addDays(20 + $i)->toDateString()]], range(1, 6)));
$wins = collect($res)->where('ok', true)->count();
$riwayat = DB::table('kegiatan_jadwal_riwayat')->where('kegiatan_id', $kegiatan->id)->count();
$report('6 parallel reschedules of one version: one wins, one history row', $wins === 1 && $riwayat === 1, "winners={$wins} history_rows={$riwayat}");

DB::purge();
$admin->exec("DROP DATABASE IF EXISTS {$dbName}");
echo $fail ? "\nRESULT: FAIL\n" : "\nRESULT: ALL PASS\n";
exit($fail ? 1 : 0);

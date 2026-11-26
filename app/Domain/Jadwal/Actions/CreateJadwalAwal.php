<?php

namespace App\Domain\Jadwal\Actions;

use App\Domain\Jadwal\Events\JadwalDibuat;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Jadwal\Support\KegiatanRules;
use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Schedules the single initial training of a school (FR-JDA-01..03, BR-AWL-01..03).
 * Method and location are fixed: Offline at the central campus; only the room detail is free text.
 */
final class CreateJadwalAwal
{
    public const NAMA = 'Pelatihan Awal';

    /**
     * @param  array<string, mixed>  $input  tanggal, waktu_mulai, waktu_selesai, jenis_kegiatan_id,
     *                                       [mentor_pelaksana_id], [detail_lokasi], [keterangan]
     */
    public function handle(User $actor, Sekolah $sekolah, array $input): Kegiatan
    {
        Gate::forUser($actor)->authorize('createAwal', $sekolah);

        $data = KegiatanRules::validate($input, ['detail_lokasi' => ['nullable', 'string', 'max:500']]);
        $requested = filled($data['mentor_pelaksana_id'] ?? null) ? (int) $data['mentor_pelaksana_id'] : null;

        $metode = MetodeKegiatan::query()->where('nama', 'Offline')->first();
        $kampus = AppSetting::get(AppSetting::LOKASI_KAMPUS_PUSAT);

        if ($metode === null || blank($kampus)) {
            throw DomainRuleViolation::withMessage('Lokasi kampus pusat belum diatur. Hubungi Superadmin.');
        }

        $kegiatan = DB::transaction(function () use ($actor, $sekolah, $data, $requested, $metode, $kampus) {
            /** @var Sekolah $locked */
            $locked = Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();

            // AC-JDA-02 / AC-JDA-08: one initial training that is not cancelled (planned or done).
            if ($locked->kegiatan()->where('tahap', Tahap::Awal->value)->where('status', '<>', StatusKegiatan::Dibatalkan->value)->exists()) {
                throw DomainRuleViolation::withMessage('Sekolah sudah memiliki pelatihan awal.');
            }

            [$pelaksanaId, $khusus] = KegiatanRules::resolveExecutor($actor, $locked, $requested);

            $lokasi = trim((string) $kampus);

            if (filled($data['detail_lokasi'] ?? null)) {
                $lokasi .= ' — '.trim((string) $data['detail_lokasi']);
            }

            $kegiatan = Kegiatan::create([
                'sekolah_id' => $locked->id,
                'tahap' => Tahap::Awal,
                // A cancelled attempt keeps its number; the new one follows it (AC-JDA-03).
                'urutan' => (int) $locked->kegiatan()->where('tahap', Tahap::Awal->value)->max('urutan') + 1,
                'nama_sesi' => self::NAMA,
                'jenis_kegiatan_id' => $data['jenis_kegiatan_id'],
                'metode_id' => $metode->id,
                'tanggal' => $data['tanggal'],
                'waktu_mulai' => $data['waktu_mulai'],
                'waktu_selesai' => $data['waktu_selesai'],
                'lokasi' => $lokasi,
                'mentor_pelaksana_id' => $pelaksanaId,
                'pelaksana_ditunjuk_khusus' => $khusus,
                'keterangan' => filled($data['keterangan'] ?? null) ? trim((string) $data['keterangan']) : null,
                'status' => StatusKegiatan::BelumDimulai,
                'created_by' => $actor->id,
            ]);

            AuditWriter::record('kegiatan.create', $actor, $kegiatan, null, null, [
                'sekolah_id' => $locked->id,
                'tahap' => Tahap::Awal->value,
                'tanggal' => $kegiatan->tanggal->toDateString(),
                'mentor_pelaksana_id' => $pelaksanaId,
            ]);

            // Hydrate database defaults (version, flags) so callers can pass the version on.
            return $kegiatan->refresh();
        });

        DB::afterCommit(fn () => JadwalDibuat::dispatch($kegiatan->id, $actor->id));

        return $kegiatan;
    }
}

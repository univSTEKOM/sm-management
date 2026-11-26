<?php

namespace App\Domain\Jadwal\Actions;

use App\Domain\Jadwal\Events\JadwalDibuat;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Jadwal\Support\KegiatanRules;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Progress\ProgressRecalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a follow-up session (FR-JTL-01..03, FR-JTL-06, BR-TL-01..05, BR-TL-10, BR-ACT-12).
 * No limit on the number of sessions and no check on the online/offline mix (BR-TL-02).
 */
final class CreateSesiTindakLanjut
{
    /**
     * @param  array<string, mixed>  $input  tanggal, waktu_mulai, waktu_selesai, jenis_kegiatan_id, metode_id,
     *                                       [lokasi], [tautan_pertemuan], [nama_sesi], [mentor_pelaksana_id], [keterangan]
     */
    public function handle(User $actor, Sekolah $sekolah, array $input): Kegiatan
    {
        Gate::forUser($actor)->authorize('createTl', $sekolah);

        $data = KegiatanRules::validate($input, [
            'metode_id' => ['required', 'integer', 'exists:metode_kegiatan,id'],
            'lokasi' => ['nullable', 'string', 'max:1000'],
            'tautan_pertemuan' => ['nullable', 'url:http,https', 'max:1000'],
            'nama_sesi' => ['nullable', 'string', 'max:150'],
        ]);

        $metode = MetodeKegiatan::query()->where('aktif', true)->find($data['metode_id']);

        if ($metode === null) {
            throw DomainRuleViolation::withMessage('Pilih metode yang aktif.', 'metode_id');
        }

        KegiatanRules::assertMethodFields($metode, $data['lokasi'] ?? null, $data['tautan_pertemuan'] ?? null);

        $requested = filled($data['mentor_pelaksana_id'] ?? null) ? (int) $data['mentor_pelaksana_id'] : null;

        $kegiatan = DB::transaction(function () use ($actor, $sekolah, $data, $metode, $requested) {
            /** @var Sekolah $locked */
            $locked = Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();

            // BR-TL-05 / AC-JTL-01: only after the initial training is Selesai.
            if (! $locked->kegiatan()->where('tahap', Tahap::Awal->value)->where('status', StatusKegiatan::Selesai->value)->exists()) {
                throw DomainRuleViolation::withMessage('Sesi tindak lanjut hanya dapat dibuat setelah pelatihan awal selesai.');
            }

            if ($locked->posisi_tahap === PosisiTahap::SelesaiProgram) {
                throw DomainRuleViolation::withMessage('Rangkaian tindak lanjut sudah selesai. Admin Pusat perlu membukanya kembali sebelum menambah sesi.');
            }

            // BR-TL-10 / AC-JTL-13: a last session that is not cancelled closes the list to mentors.
            // A cancelled last session no longer counts (BR-TL-12).
            $sesiTerakhirBerlaku = $locked->kegiatan()
                ->where('tahap', Tahap::TindakLanjut->value)
                ->where('is_sesi_terakhir', true)
                ->where('status', '<>', StatusKegiatan::Dibatalkan->value)
                ->exists();

            if ($sesiTerakhirBerlaku) {
                Gate::forUser($actor)->authorize('createTlAfterLast', $locked);
            }

            [$pelaksanaId, $khusus] = KegiatanRules::resolveExecutor($actor, $locked, $requested);

            // AC-JTL-02: next number is the largest plus one (cancelled sessions keep theirs).
            $urutan = (int) $locked->kegiatan()->where('tahap', Tahap::TindakLanjut->value)->max('urutan') + 1;

            $kegiatan = Kegiatan::create([
                'sekolah_id' => $locked->id,
                'tahap' => Tahap::TindakLanjut,
                'urutan' => $urutan,
                'nama_sesi' => filled($data['nama_sesi'] ?? null) ? trim((string) $data['nama_sesi']) : 'Sesi '.$urutan,
                'jenis_kegiatan_id' => $data['jenis_kegiatan_id'],
                'metode_id' => $metode->id,
                'tanggal' => $data['tanggal'],
                'waktu_mulai' => $data['waktu_mulai'],
                'waktu_selesai' => $data['waktu_selesai'],
                'lokasi' => $metode->butuh_lokasi ? trim((string) $data['lokasi']) : null,
                'tautan_pertemuan' => $metode->butuh_tautan ? trim((string) $data['tautan_pertemuan']) : null,
                'mentor_pelaksana_id' => $pelaksanaId,
                'pelaksana_ditunjuk_khusus' => $khusus,
                'keterangan' => filled($data['keterangan'] ?? null) ? trim((string) $data['keterangan']) : null,
                'status' => StatusKegiatan::BelumDimulai,
                'created_by' => $actor->id,
            ]);

            // The first session moves the school from "Mitra – Belum Tindak Lanjut" to "Mitra – Tindak Lanjut" (PRD 15.3).
            ProgressRecalculator::forSekolah($locked);

            AuditWriter::record('kegiatan.create', $actor, $kegiatan, null, null, [
                'sekolah_id' => $locked->id,
                'tahap' => Tahap::TindakLanjut->value,
                'urutan' => $urutan,
                'nama_sesi' => $kegiatan->nama_sesi,
                'metode_id' => $metode->id,
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

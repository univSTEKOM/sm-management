<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sekolah\Support\NamaSekolahNormalizer;
use App\Domain\Shared\Enums\Jenjang;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Updates school data except the branch (BR-SCH-10: no transfer; the branch
 * changes only through CorrectSekolahCabang by Superadmin).
 */
final class UpdateSekolah
{
    private const FIELDS = ['nama', 'jenjang', 'alamat', 'kabupaten_kota_id'];

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Sekolah $sekolah, array $input, ?int $expectedVersion = null): Sekolah
    {
        Gate::forUser($actor)->authorize('update', $sekolah);

        $data = Validator::make(Arr::only($input, [...self::FIELDS, 'kategori_ids']), [
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'jenjang' => ['sometimes', 'required', Rule::enum(Jenjang::class)],
            'alamat' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // FR-MST-02: an inactive kabupaten/kota is not offered anymore, but the current one may stay.
            'kabupaten_kota_id' => ['sometimes', 'required', 'integer', Rule::exists('kabupaten_kota', 'id')
                ->where(fn ($q) => $q->where('aktif', true)->orWhere('id', $sekolah->kabupaten_kota_id))],
            'kategori_ids' => ['sometimes', 'array'],
            'kategori_ids.*' => ['integer', Rule::exists('kategori_pelatihan', 'id')->where('aktif', true)],
        ])->validate();

        return DB::transaction(function () use ($actor, $sekolah, $data, $expectedVersion) {
            /** @var Sekolah $locked */
            $locked = Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();
            OptimisticLock::check($locked, $expectedVersion);

            $before = $locked->only(self::FIELDS) + ['kategori_ids' => $locked->kategori()->pluck('kategori_pelatihan.id')->all()];

            $locked->fill(Arr::only($data, self::FIELDS));

            if (isset($data['nama'])) {
                $locked->nama = trim($data['nama']);
                $locked->nama_normal = NamaSekolahNormalizer::normalize($data['nama']);
            }

            DuplicateSekolahGuard::assertUnique(
                $locked->nama_normal,
                $locked->jenjang->value,
                (int) $locked->kabupaten_kota_id,
                $locked->id,
            );

            if (array_key_exists('kategori_ids', $data)) {
                Gate::forUser($actor)->authorize('updateKategori', $locked);
                $locked->kategori()->sync($data['kategori_ids']);
            }

            OptimisticLock::bump($locked);
            $locked->save();

            $after = $locked->only(self::FIELDS) + ['kategori_ids' => $locked->kategori()->pluck('kategori_pelatihan.id')->all()];
            AuditWriter::record('sekolah.update', $actor, $locked, null, $before, $after);

            return $locked;
        });
    }
}

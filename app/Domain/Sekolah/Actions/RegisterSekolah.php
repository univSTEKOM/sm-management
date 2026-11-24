<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Master\Models\Cabang;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\NarahubungRiwayat;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sekolah\Support\NamaSekolahNormalizer;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Registers a school (FR-SCH-01..05, BR-SCH-03/06/09).
 * Admin Cabang always registers into their own branch; the branch is locked afterwards.
 */
final class RegisterSekolah
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Sekolah
    {
        Gate::forUser($actor)->authorize('create', Sekolah::class);

        if ($actor->role === Role::AdminCabang) {
            $input['cabang_id'] = $actor->cabang_id;
        }

        $data = Validator::make($input, [
            'nama' => ['required', 'string', 'max:255'],
            'jenjang' => ['required', Rule::enum(Jenjang::class)],
            'alamat' => ['nullable', 'string', 'max:2000'],
            'kabupaten_kota_id' => ['required', 'integer', Rule::exists('kabupaten_kota', 'id')->where('aktif', true)],
            'cabang_id' => ['required', 'integer', Rule::exists('cabang', 'id')->where('aktif', true)],
            'kategori_ids' => ['array'],
            'kategori_ids.*' => ['integer', Rule::exists('kategori_pelatihan', 'id')->where('aktif', true)],
            'narahubung_utama' => ['required', 'array'],
            'narahubung_utama.nama' => ['required', 'string', 'max:255'],
            'narahubung_utama.nomor_wa' => ManageNarahubung::rules()['nomor_wa'],
            'narahubung_utama.jabatan' => ['nullable', 'string', 'max:255'],
            'narahubung_utama.email' => ['nullable', 'email', 'max:255'],
        ], [
            'narahubung_utama.nomor_wa.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda hubung, dan awalan +.',
        ], [
            'narahubung_utama.nama' => 'nama narahubung utama',
            'narahubung_utama.nomor_wa' => 'nomor WhatsApp narahubung utama',
        ])->validate();

        $namaNormal = NamaSekolahNormalizer::normalize($data['nama']);

        DuplicateSekolahGuard::assertUnique($namaNormal, $data['jenjang'], (int) $data['kabupaten_kota_id']);

        return DB::transaction(function () use ($actor, $data, $namaNormal) {
            $sekolah = Sekolah::create([
                'nama' => trim($data['nama']),
                'nama_normal' => $namaNormal,
                'jenjang' => $data['jenjang'],
                'alamat' => $data['alamat'] ?? null,
                'kabupaten_kota_id' => $data['kabupaten_kota_id'],
                'cabang_id' => Cabang::findOrFail($data['cabang_id'])->id,
                'posisi_tahap' => PosisiTahap::CalonMitra,
                'created_by' => $actor->id,
            ]);

            $sekolah->kategori()->sync($data['kategori_ids'] ?? []);

            $narahubung = Narahubung::create([
                'sekolah_id' => $sekolah->id,
                'nama' => $data['narahubung_utama']['nama'],
                'nomor_wa' => $data['narahubung_utama']['nomor_wa'],
                'jabatan' => $data['narahubung_utama']['jabatan'] ?? null,
                'email' => $data['narahubung_utama']['email'] ?? null,
                'is_utama' => true,
                'aktif' => true,
            ]);

            // Every contact change, including the first one, is kept in the history (BR-SCH-06).
            NarahubungRiwayat::create([
                'narahubung_id' => $narahubung->id,
                'data_sebelum' => null,
                'data_sesudah' => $narahubung->snapshot(),
                'diubah_oleh' => $actor->id,
            ]);

            AuditWriter::record('sekolah.create', $actor, $sekolah, null, null, $sekolah->fresh()->toArray() + [
                'kategori_ids' => $data['kategori_ids'] ?? [],
            ]);

            return $sekolah;
        });
    }
}

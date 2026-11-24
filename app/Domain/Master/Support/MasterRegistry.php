<?php

namespace App\Domain\Master\Support;

use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Master\Models\MetodeKegiatan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Master data types managed by Superadmin (FR-MST-01, PRD 16.10): editable fields,
 * validation rules and the tables that reference each type (FR-MST-02 usage check).
 */
final class MasterRegistry
{
    /** @var array<class-string<Model>, array{fields: list<string>, usage: list<array{string, string}>}> */
    private const TYPES = [
        Cabang::class => [
            'fields' => ['kode', 'nama', 'alamat'],
            'usage' => [['sekolah', 'cabang_id'], ['users', 'cabang_id'], ['sekolah_cabang_koreksi', 'cabang_lama_id'], ['sekolah_cabang_koreksi', 'cabang_baru_id']],
        ],
        KabupatenKota::class => [
            'fields' => ['kode', 'nama'],
            'usage' => [['sekolah', 'kabupaten_kota_id']],
        ],
        KategoriPelatihan::class => [
            'fields' => ['nama', 'deskripsi'],
            'usage' => [['sekolah_kategori', 'kategori_id']],
        ],
        JenisKegiatan::class => [
            'fields' => ['nama'],
            'usage' => [['kegiatan', 'jenis_kegiatan_id']],
        ],
        MetodeKegiatan::class => [
            'fields' => ['nama', 'butuh_lokasi', 'butuh_tautan'],
            'usage' => [['kegiatan', 'metode_id']],
        ],
    ];

    /** @return list<class-string<Model>> */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    public static function supports(Model|string $model): bool
    {
        return isset(self::TYPES[is_string($model) ? $model : $model::class]);
    }

    /** @return list<string> */
    public static function fields(string $modelClass): array
    {
        return self::type($modelClass)['fields'];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(string $modelClass, ?int $ignoreId = null): array
    {
        $table = (new $modelClass)->getTable();
        $unique = fn (string $column) => Rule::unique($table, $column)->ignore($ignoreId);

        return match ($modelClass) {
            Cabang::class => [
                'kode' => ['required', 'string', 'max:20', $unique('kode')],
                'nama' => ['required', 'string', 'max:255'],
                'alamat' => ['nullable', 'string', 'max:2000'],
            ],
            KabupatenKota::class => [
                'kode' => ['required', 'string', 'max:10', $unique('kode')],
                'nama' => ['required', 'string', 'max:255'],
            ],
            KategoriPelatihan::class => [
                'nama' => ['required', 'string', 'max:255', $unique('nama')],
                'deskripsi' => ['nullable', 'string', 'max:2000'],
            ],
            JenisKegiatan::class => [
                'nama' => ['required', 'string', 'max:255', $unique('nama')],
            ],
            MetodeKegiatan::class => [
                'nama' => ['required', 'string', 'max:255', $unique('nama')],
                'butuh_lokasi' => ['boolean'],
                'butuh_tautan' => ['boolean'],
            ],
            default => throw new InvalidArgumentException("Unsupported master type [{$modelClass}]."),
        };
    }

    /** FR-MST-02: a record is "in use" when any business table references it. */
    public static function isUsed(Model $record): bool
    {
        foreach (self::type($record::class)['usage'] as [$table, $column]) {
            if (DB::table($table)->where($column, $record->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @return array{fields: list<string>, usage: list<array{string, string}>} */
    private static function type(string $modelClass): array
    {
        return self::TYPES[$modelClass] ?? throw new InvalidArgumentException("Unsupported master type [{$modelClass}].");
    }
}

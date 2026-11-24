<?php

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Key/value application settings managed by Superadmin (halaman Setting).
 *
 * @property string $key
 * @property mixed $value
 * @property int|null $diubah_oleh
 */
#[Fillable(['key', 'value', 'diubah_oleh'])]
class AppSetting extends Model
{
    /** Default location of Pelatihan Awal (FR-JDA-03, BR-AWL-02). */
    public const LOKASI_KAMPUS_PUSAT = 'lokasi_kampus_pusat';

    protected $table = 'app_settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->find($key)->value ?? $default;
    }
}

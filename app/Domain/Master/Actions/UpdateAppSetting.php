<?php

namespace App\Domain\Master\Actions;

use App\Domain\Master\Models\AppSetting;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** Updates an application setting (Superadmin, `master.manage`). Only known keys are accepted. */
final class UpdateAppSetting
{
    /** @var array<string, list<string>> */
    private const RULES = [
        AppSetting::LOKASI_KAMPUS_PUSAT => ['required', 'string', 'max:500'],
    ];

    public function handle(User $actor, string $key, mixed $value): AppSetting
    {
        if (! array_key_exists($key, self::RULES)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        Gate::forUser($actor)->authorize('manageSettings', AppSetting::class);

        $value = is_string($value) ? trim($value) : $value;
        Validator::make(['value' => $value], ['value' => self::RULES[$key]])->validate();

        return DB::transaction(function () use ($actor, $key, $value) {
            /** @var AppSetting|null $setting */
            $setting = AppSetting::query()->whereKey($key)->lockForUpdate()->first();
            $before = $setting?->value;

            $setting ??= new AppSetting(['key' => $key]);
            $setting->fill(['value' => $value, 'diubah_oleh' => $actor->id])->save();

            AuditWriter::record('setting.update', $actor, 'app_settings', $key, ['value' => $before], ['value' => $value]);

            return $setting;
        });
    }
}

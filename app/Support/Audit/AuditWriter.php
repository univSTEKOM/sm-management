<?php

namespace App\Support\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the append-only audit trail (FR-AUD-01). Domain actions call it inside
 * their transaction so audit and data commit together.
 */
final class AuditWriter
{
    /**
     * @param  array<string, mixed>|null  $sebelum
     * @param  array<string, mixed>|null  $sesudah
     */
    public static function record(
        string $aksi,
        ?User $actor = null,
        Model|string|null $entitas = null,
        int|string|null $entitasId = null,
        ?array $sebelum = null,
        ?array $sesudah = null,
    ): AuditLog {
        $actor ??= auth()->user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        if ($entitas instanceof Model) {
            $entitasId ??= $entitas->getKey();
            $entitas = $entitas->getTable();
        }

        return AuditLog::create([
            'occurred_at' => now(),
            'user_id' => $actor?->getKey(),
            'role_saat_aksi' => $actor?->role?->value,
            'aksi' => $aksi,
            'entitas' => $entitas,
            'entitas_id' => $entitasId === null ? null : (string) $entitasId,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}

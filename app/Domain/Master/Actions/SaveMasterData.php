<?php

namespace App\Domain\Master\Actions;

use App\Domain\Master\Support\MasterRegistry;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** Creates or updates a master data record (FR-MST-01, Superadmin only). */
final class SaveMasterData
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, mixed>  $input
     * @param  TModel|null  $record
     * @return TModel
     */
    public function handle(User $actor, string $modelClass, array $input, ?Model $record = null): Model
    {
        if (! MasterRegistry::supports($modelClass) || ($record !== null && ! $record instanceof $modelClass)) {
            throw new InvalidArgumentException("Unsupported master type [{$modelClass}].");
        }

        $record === null
            ? Gate::forUser($actor)->authorize('create', $modelClass)
            : Gate::forUser($actor)->authorize('update', $record);

        $fields = MasterRegistry::fields($modelClass);
        $input = array_map(fn ($value) => is_string($value) ? trim($value) : $value, Arr::only($input, $fields));
        $data = Validator::make($input, MasterRegistry::rules($modelClass, $record?->getKey()))->validate();

        return DB::transaction(function () use ($actor, $modelClass, $data, $record, $fields) {
            if ($record === null) {
                /** @var Model $created */
                $created = $modelClass::query()->create($data + ['aktif' => true]);
                AuditWriter::record('master.create', $actor, $created, null, null, $created->only([...$fields, 'aktif']));

                return $created;
            }

            /** @var Model $locked */
            $locked = $modelClass::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $before = $locked->only($fields);
            $locked->fill($data)->save();
            AuditWriter::record('master.update', $actor, $locked, null, $before, $locked->only($fields));

            return $locked;
        });
    }
}

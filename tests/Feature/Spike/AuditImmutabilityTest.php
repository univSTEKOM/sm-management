<?php

/*
 * BR-AUD-03 / AC-AUD-03: audit entries cannot be changed or deleted through
 * any application path.
 */

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->entry = AuditWriter::record('uji', User::factory()->create(), 'users', 1, null, ['a' => 1]);
});

test('model update is refused', function () {
    expect(fn () => $this->entry->update(['aksi' => 'ubah']))->toThrow(LogicException::class);
});

test('model delete is refused', function () {
    expect(fn () => $this->entry->delete())->toThrow(LogicException::class);
});

test('query builder update is refused by the database', function () {
    expect(fn () => AuditLog::query()->whereKey($this->entry->id)->update(['aksi' => 'ubah']))
        ->toThrow(QueryException::class, 'append-only');
});

test('query builder delete is refused by the database', function () {
    expect(fn () => AuditLog::query()->whereKey($this->entry->id)->delete())->toThrow(QueryException::class, 'append-only');
});

test('raw SQL delete is refused by the database', function () {
    expect(fn () => DB::delete('delete from audit_log'))->toThrow(QueryException::class, 'append-only');
});

test('truncate is refused by the database', function () {
    expect(fn () => DB::statement('truncate audit_log'))->toThrow(QueryException::class, 'append-only');
});

test('entries survive and can still be inserted', function () {
    AuditWriter::record('uji-2');

    expect(AuditLog::count())->toBe(2)
        ->and($this->entry->fresh()->aksi)->toBe('uji');
});

test('no route exposes audit modification', function () {
    $writes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'audit'))
        ->filter(fn ($route) => array_intersect($route->methods(), ['PUT', 'PATCH', 'DELETE', 'POST']) !== []);

    expect($writes)->toBeEmpty();
});

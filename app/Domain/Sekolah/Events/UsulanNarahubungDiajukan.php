<?php

namespace App\Domain\Sekolah\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched after commit; listeners notify recipients other than the actor (BR-NTF-02). */
final class UsulanNarahubungDiajukan
{
    use Dispatchable;

    public function __construct(
        public readonly int $usulanId,
        public readonly int $actorId,
    ) {}
}

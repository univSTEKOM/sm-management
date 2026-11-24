<?php

namespace App\Domain\Mentor\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched after commit; listeners notify recipients other than the actor (BR-NTF-02). */
final class MentorDitugaskan
{
    use Dispatchable;

    public function __construct(
        public readonly int $penugasanId,
        public readonly int $actorId,
    ) {}
}

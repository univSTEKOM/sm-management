<?php

namespace App\Domain\Shared\Exceptions;

use Illuminate\Validation\ValidationException;

/** A business rule rejected the action; rendered to users as a validation error. */
final class DomainRuleViolation
{
    public static function withMessage(string $message, string $field = 'domain'): ValidationException
    {
        return ValidationException::withMessages([$field => $message]);
    }
}

<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Runs a domain action from a Filament action and shows business-rule
 * violations as a notification. Authorization failures are not caught: they
 * surface as HTTP 403.
 */
final class RunsDomainAction
{
    public static function run(Action $action, Closure $callback, string $successTitle): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Tidak dapat disimpan')
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->send();

            $action->halt();
        }

        Notification::make()->success()->title($successTitle)->send();
    }

    /**
     * Runs a domain action inside a Filament create/edit process (`->using()`), returning its
     * result. Business-rule violations keep the modal open; Filament sends its own success
     * notification.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function attempt(Action $action, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Tidak dapat disimpan')
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->send();

            $action->halt();

            throw $e;
        }
    }
}

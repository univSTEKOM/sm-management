<?php

namespace App\Support\Session;

use Filament\Livewire\DatabaseNotifications;
use Illuminate\Http\Request;

/**
 * Decides whether a request is real user activity for the idle timeout (TD-04).
 *
 * Deny-by-default: everything is activity unless it is a Livewire update whose
 * every component is the Filament database-notifications component, carries no
 * property updates, and only calls the polling refresh. Any other call on that
 * component (mark as read, clear) or any mixed request counts as activity.
 * A spoofed "passive" request can only make a session expire sooner, never
 * keep it alive longer.
 */
final class ActivityClassifier
{
    /**
     * Livewire component names whose polling is passive. Livewire 4 snapshots
     * carry the component class name (verified in SP-06).
     */
    private const PASSIVE_COMPONENTS = [DatabaseNotifications::class];

    private const POLL_METHOD = '$refresh';

    public function isUserActivity(Request $request): bool
    {
        return ! $this->isPassivePoll($request);
    }

    public function isPassivePoll(Request $request): bool
    {
        if (! $request->hasHeader('X-Livewire')) {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            if (! $this->isPassiveComponentPayload($component)) {
                return false;
            }
        }

        return true;
    }

    private function isPassiveComponentPayload(mixed $component): bool
    {
        if (! is_array($component) || ! is_string($component['snapshot'] ?? null)) {
            return false;
        }

        $snapshot = json_decode($component['snapshot'], true);
        $name = is_array($snapshot) ? ($snapshot['memo']['name'] ?? null) : null;

        if (! in_array($name, self::PASSIVE_COMPONENTS, true)) {
            return false;
        }

        if (($component['updates'] ?? null) !== []) {
            return false;
        }

        $calls = $component['calls'] ?? null;

        if (! is_array($calls) || $calls === []) {
            return false;
        }

        foreach ($calls as $call) {
            if (! is_array($call) || ($call['method'] ?? null) !== self::POLL_METHOD) {
                return false;
            }
        }

        return true;
    }
}

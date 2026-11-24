<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use RuntimeException;

/**
 * Builds raw Livewire update requests so tests exercise the real HTTP endpoint
 * (persistent middleware included), not only component helpers.
 */
final class LivewireHttp
{
    /** Snapshot JSON of the first component with the given Livewire name in an HTML page. */
    public static function snapshot(string $html, string $componentName): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $json = html_entity_decode($encoded, ENT_QUOTES);
            $data = json_decode($json, true);

            if (($data['memo']['name'] ?? null) === $componentName) {
                return $json;
            }
        }

        throw new RuntimeException("Component [{$componentName}] not found in page.");
    }

    /** @return list<string> */
    public static function componentNames(string $html): array
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        return array_values(array_map(
            fn (string $encoded) => json_decode(html_entity_decode($encoded, ENT_QUOTES), true)['memo']['name'] ?? '?',
            $matches[1],
        ));
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  list<array<string, mixed>>  $calls
     * @return array<string, mixed>
     */
    public static function component(string $snapshot, array $updates = [], array $calls = []): array
    {
        return ['snapshot' => $snapshot, 'updates' => (object) $updates, 'calls' => $calls];
    }

    /** @return array<string, mixed> */
    public static function pollCall(): array
    {
        return ['method' => '$refresh', 'params' => [], 'metadata' => ['type' => 'poll']];
    }

    /** @return array<string, mixed> */
    public static function call(string $method, array $params = []): array
    {
        return ['method' => $method, 'params' => $params, 'metadata' => []];
    }

    /**
     * Livewire skips persistent middleware for a route it just processed until
     * its state is flushed. Under PHP-FPM every request is a fresh process; the
     * test client reuses one application, so flush to mimic a new request.
     */
    public static function freshRequestState(): void
    {
        app('livewire')->flushState();
    }

    public static function updateUri(): string
    {
        return app('livewire')->getUpdateUri();
    }

    /** Session cookie of a response, to carry the session into the next request. */
    public static function sessionCookie(TestResponse $response): ?string
    {
        return $response->getCookie(config('session.cookie'))?->getValue();
    }
}

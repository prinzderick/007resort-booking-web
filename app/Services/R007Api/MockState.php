<?php

namespace App\Services\R007Api;

use Illuminate\Support\Facades\Cache;

/**
 * Mock API mode state. Lives in the cache store (not a database): it is a
 * development/demo stand-in for the API, wiped with `php artisan cache:clear`.
 */
final class MockState
{
    private const KEY = 'r007.mock.state';

    /** @return array<string, mixed> */
    public static function get(): array
    {
        return Cache::get(self::KEY, [
            'customers' => [], 'tokens' => [], 'bookings' => [], 'entitlements' => [],
            'payments' => [], 'orders' => [], 'memberships' => [], 'idem' => [], 'seq' => 0,
        ]);
    }

    /** @param array<string, mixed> $state */
    public static function put(array $state): void
    {
        Cache::put(self::KEY, $state, now()->addDay());
    }

    /** @return array<string, mixed>|null */
    public static function payment(string $reference): ?array
    {
        return self::get()['payments'][$reference] ?? null;
    }

    /** Simulate the provider settling a payment (webhook + capture side effects). */
    public static function settlePayment(string $reference, string $status): ?array
    {
        return (new MockR007ApiClient([]))->settle($reference, $status);
    }
}

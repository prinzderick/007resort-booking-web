<?php

namespace Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Base for feature tests that talk to a faked 007 Resort & Spa API.
 * Fixtures follow the OpenAPI contract (and the PROPOSED customer/public
 * endpoints documented in docs/API_DEPENDENCIES.md).
 */
abstract class ApiTestCase extends TestCase
{
    public const SPORTS = '0192f6a0-0000-7000-8000-000000000101';

    public const SPA = '0192f6a0-0000-7000-8000-000000000102';

    public const POOL = '0192f6a0-0000-7000-8000-000000000104';

    public const COURT = '0192f6a0-0000-7000-8000-000000000201';

    public const BOOKING = '0192f6a0-0000-7000-8000-000000000501';

    public const ENT = '0192f6a0-0000-7000-8000-000000000801';

    protected function url(string $path): string
    {
        return 'https://api.r007.test/api/v1/'.ltrim($path, '/');
    }

    /** @param array<string, mixed> $routes url-path => response */
    protected function fakeApi(array $routes): void
    {
        $map = [];
        foreach ($routes as $path => $response) {
            $map[$this->url($path)] = $response;
        }
        $map['*'] = Http::response(['title' => 'Not faked', 'status' => 404, 'code' => 'not_found'], 404);
        Http::fake($map);
    }

    /** @return array<string, mixed> */
    protected function problem(int $status, string $code, string $title = 'Problem', ?string $detail = null): array
    {
        return ['type' => 'https://api.007resort.com/problems/'.$code, 'title' => $title, 'status' => $status, 'code' => $code, 'detail' => $detail];
    }

    protected function problemResponse(int $status, string $code, ?string $detail = null)
    {
        return Http::response($this->problem($status, $code, ucfirst(str_replace('_', ' ', $code)), $detail), $status, ['Content-Type' => 'application/problem+json']);
    }

    /** @return array<string, mixed> */
    protected function siteBody(array $facilityOverrides = []): array
    {
        $f = fn (string $id, string $kind, string $name, array $o = []) => $o + ['id' => $id, 'code' => $kind, 'kind' => $kind, 'name' => $name, 'onlineBookable' => true];

        return [
            'contact' => ['phone' => '+234 800 000 0001', 'email' => 'hello@007.test', 'address' => '1 Resort Road'],
            'openingHours' => 'Daily 08:00-22:00',
            'facilities' => [
                $f(self::SPORTS, 'SPORTS_ARENA', 'Sports Arena', $facilityOverrides['sports'] ?? []),
                $f(self::SPA, 'SPA', 'Beauty Spa', $facilityOverrides['spa'] ?? []),
                $f(self::POOL, 'POOL', 'Swimming Pool', $facilityOverrides['pool'] ?? []),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function court(array $o = []): array
    {
        return $o + ['id' => self::COURT, 'facilityId' => self::SPORTS, 'name' => 'Tennis Court 1', 'mode' => 'TIME_SLOT', 'capacity' => 1, 'slotMinutes' => 60, 'price' => '5000.0000', 'active' => true];
    }

    /** @return array<string, mixed> */
    protected function booking(array $o = []): array
    {
        return $o + [
            'id' => self::BOOKING, 'number' => 'BK-20260923-0007', 'resourceId' => self::COURT, 'resourceName' => 'Tennis Court 1', 'facilityId' => self::SPORTS,
            'start' => now()->addDays(3)->setTime(9, 0)->utc()->format('Y-m-d\TH:i:s\Z'), 'end' => now()->addDays(3)->setTime(10, 0)->utc()->format('Y-m-d\TH:i:s\Z'),
            'quantity' => 1, 'status' => 'HELD', 'holdExpiresAt' => now()->addMinutes(10)->utc()->format('Y-m-d\TH:i:s\Z'),
            'total' => '5000.0000', 'amountPaid' => '0.0000', 'entitlementId' => null, 'rowVersion' => 3, 'source' => 'ONLINE',
        ];
    }

    /** @return array<string, mixed> */
    protected function entitlement(array $o = []): array
    {
        return $o + [
            'id' => self::ENT, 'qrToken' => 'signed.qr.token.abc123', 'status' => 'ACTIVE', 'orderId' => null, 'bookingId' => self::BOOKING, 'holderName' => 'Chinedu Eze',
            'items' => [['id' => Str::uuid()->toString(), 'kind' => 'ACCESS', 'name' => 'Tennis Court 1 - 09:00', 'facilityId' => self::SPORTS, 'quantity' => 1, 'quantityRedeemed' => 0, 'validationMode' => 'SINGLE_USE', 'validFrom' => '2026-09-24T08:45:00Z', 'validUntil' => '2026-09-24T10:00:00Z', 'rentalStatus' => null]],
            'issuedAt' => '2026-09-23T10:15:30.123456Z',
        ];
    }

    protected function page(array $items)
    {
        return Http::response(['items' => $items, 'nextCursor' => null]);
    }

    protected function signIn(): static
    {
        return $this->withSession([
            'r007.api_token' => 'cust-token',
            'r007.customer' => ['id' => '0192f6a0-0000-7000-8000-000000000901', 'name' => 'Chinedu Eze', 'email' => 'chinedu@example.com', 'phone' => '+2348012345678', 'emailVerified' => true],
        ]);
    }

    protected function pending(string $reference, array $context): static
    {
        return $this->withSession(['checkout.pending.'.$reference => $context + ['attempts' => 0]]);
    }

    protected function sentTo(string $method, string $pathContains): array
    {
        return Http::recorded(fn ($req) => $req->method() === $method && str_contains($req->url(), $pathContains))->all();
    }
}

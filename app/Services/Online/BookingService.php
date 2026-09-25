<?php

namespace App\Services\Online;

use App\Services\R007Api\R007ApiClient;
use App\Support\Lagos;
use Carbon\CarbonImmutable;

/**
 * Thin mapping over the API booking engine. There is NO availability, pricing
 * or policy logic here: everything shown is what the API returned, and every
 * decision (hold winner, cancellation eligibility, fees) is the API's.
 */
class BookingService
{
    public function __construct(private readonly R007ApiClient $api) {}

    /**
     * @param  string|list<string>  $facilityIds  one API facility or every facility behind a page (e.g. Male + Female salon)
     * @return list<array<string, mixed>>
     */
    public function resources(string|array $facilityIds): array
    {
        $out = [];
        foreach ((array) $facilityIds as $facilityId) {
            foreach ($this->api->all('bookings/resources', ['facilityId' => $facilityId]) as $r) {
                if (($r['active'] ?? true) !== false) {
                    $out[$r['id']] = $r;
                }
            }
        }

        return array_values($out);
    }

    /** @return array<string, mixed>|null */
    public function resource(string|array $facilityId, string $resourceId): ?array
    {
        foreach ($this->resources($facilityId) as $r) {
            if (($r['id'] ?? null) === $resourceId) {
                return $r;
            }
        }

        return null;
    }

    /**
     * Slot availability for one local calendar day.
     *
     * @return list<array<string, mixed>>
     */
    public function slots(string $resourceId, CarbonImmutable $localDay): array
    {
        $body = $this->api->get("bookings/resources/{$resourceId}/availability", [
            'from' => Lagos::utcIso($localDay),
            'to' => Lagos::utcIso($localDay->addDay()),
        ]);

        return array_values((array) ($body['slots'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    public function hold(string $resourceId, string $start, string $end, int $quantity, array $customer, string $idempotencyKey, bool $guest = false): array
    {
        // Guest holds always use the website's service credential, never a customer token.
        return ($guest ? $this->api->asService() : $this->api)->post('bookings/hold', array_filter([
            'resourceId' => $resourceId,
            'start' => $start,
            'end' => $end,
            'quantity' => max(1, $quantity),
            'customer' => $customer,
        ], fn ($v) => $v !== null), $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function get(string $bookingId): array
    {
        return $this->api->get("bookings/{$bookingId}");
    }

    /** @return array<string, mixed> */
    public function cancel(array $booking, string $reason, string $idempotencyKey): array
    {
        return $this->api->post("bookings/{$booking['id']}/cancel", ['reason' => $reason], $idempotencyKey, $this->ifMatch($booking));
    }

    /** @return array<string, mixed> */
    public function reschedule(array $booking, string $start, string $end, ?string $reason, string $idempotencyKey): array
    {
        return $this->api->post("bookings/{$booking['id']}/reschedule", array_filter([
            'start' => $start,
            'end' => $end,
            'reason' => $reason,
        ]), $idempotencyKey, $this->ifMatch($booking));
    }

    /** @return array<string, mixed> */
    public function confirmWithPaystack(array $booking, string $reference): array
    {
        return $this->api->post("bookings/{$booking['id']}/confirm", ['paystackReference' => $reference], "confirm-{$booking['id']}-{$reference}", $this->ifMatch($booking));
    }

    /**
     * The signed-in customer's bookings, newest first.
     * PROPOSED API: GET /customer/bookings.
     *
     * @return list<array<string, mixed>>
     */
    public function mine(): array
    {
        return $this->api->all('customer/bookings', ['limit' => 50], 4);
    }

    /** @return array<string, string> */
    private function ifMatch(array $booking): array
    {
        return isset($booking['rowVersion']) ? ['If-Match' => '"'.$booking['rowVersion'].'"'] : [];
    }
}

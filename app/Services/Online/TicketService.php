<?php

namespace App\Services\Online;

use App\Services\R007Api\R007ApiClient;

/** Entitlements (QR tickets) and pool ticket orders. */
class TicketService
{
    public function __construct(private readonly R007ApiClient $api) {}

    /** @return array<string, mixed> */
    public function entitlement(string $id): array
    {
        return $this->api->get("entitlements/{$id}");
    }

    /**
     * Entitlements for a booking or ticket order the customer owns.
     * PROPOSED API: GET /customer/entitlements?bookingId|orderId.
     *
     * @return list<array<string, mixed>>
     */
    public function forSource(string $key, string $id): array
    {
        return $this->api->all('customer/entitlements', [$key => $id], 3);
    }

    /**
     * Ticket products (adult/child) for a facility with resolved prices.
     *
     * @return list<array<string, mixed>>
     */
    public function ticketProducts(string $facilityId): array
    {
        return array_values(array_filter(
            $this->api->all('catalog/products', ['facilityId' => $facilityId, 'filter[kind]' => 'TICKET']),
            fn ($p) => ($p['active'] ?? true) !== false,
        ));
    }

    /**
     * Create an unpaid ticket order (server prices it). PROPOSED API:
     * POST /public/ticket-orders.
     *
     * @param  list<array{productId: string, quantity: int}>  $lines
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    public function createOrder(string $facilityId, string $visitDate, array $lines, array $customer, string $idempotencyKey): array
    {
        return $this->api->post('public/ticket-orders', [
            'facilityId' => $facilityId,
            'visitDate' => $visitDate,
            'lines' => $lines,
            'customer' => $customer,
        ], $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function order(string $orderId): array
    {
        return $this->api->get("customer/orders/{$orderId}");
    }
}

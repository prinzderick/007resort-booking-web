<?php

namespace App\Services\Online;

use App\Services\R007Api\R007ApiClient;

class MembershipService
{
    public function __construct(private readonly R007ApiClient $api) {}

    /** @return list<array<string, mixed>> */
    public function plans(): array
    {
        return array_values(array_filter(
            $this->api->all('memberships/plans'),
            fn ($p) => ($p['active'] ?? true) !== false,
        ));
    }

    /**
     * Create a PENDING_PAYMENT membership; payment follows via Paystack.
     *
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    public function start(string $planId, array $customer, string $idempotencyKey): array
    {
        return $this->api->post('memberships', ['planId' => $planId, 'customer' => $customer], $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->api->get("memberships/{$id}");
    }

    /**
     * PROPOSED API: GET /customer/memberships.
     *
     * @return list<array<string, mixed>>
     */
    public function mine(): array
    {
        return $this->api->all('customer/memberships', [], 2);
    }
}

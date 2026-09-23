<?php

namespace App\Services\Online;

use App\Services\R007Api\R007ApiClient;

/**
 * Paystack via the API. The website only redirects the customer and, on
 * return, asks the API to verify: query-string parameters are never trusted.
 * Webhooks terminate at the API.
 */
class PaymentService
{
    public function __construct(private readonly R007ApiClient $api) {}

    /**
     * @param  'bookingId'|'membershipId'|'orderIds'  $subjectKey
     * @return array{paymentId: string, reference: string, authorizationUrl: string}
     */
    public function initialize(string $subjectKey, string|array $subjectId, string $amount, string $email, string $callbackUrl, string $idempotencyKey): array
    {
        return $this->api->post('payments/paystack/initialize', [
            $subjectKey => $subjectId,
            'amount' => $amount,
            'email' => $email,
            'callbackUrl' => $callbackUrl,
        ], $idempotencyKey);
    }

    /** @return array<string, mixed> Payment (status is authoritative). */
    public function verify(string $reference): array
    {
        return $this->api->get('payments/paystack/verify/'.rawurlencode($reference));
    }
}

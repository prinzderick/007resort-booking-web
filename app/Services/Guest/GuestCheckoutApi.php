<?php

namespace App\Services\Guest;

use App\Services\R007Api\R007ApiClient;

/**
 * Guest checkout over the 007 Resort & Spa API. Every call authenticates as the website's
 * SERVICE credential (never a customer token) and no account is ever created.
 *
 * Contract (see docs/GUEST_CHECKOUT.md in 007resort-api; paths are gathered here so the contract is
 * the only thing to reconcile):
 *   GET  guest/holds/{bookingId}                    read a slot hold (no personal data)
 *   POST guest/holds/{bookingId}/release            give the slot back
 *   POST guest/checkout/booking                     {bookingId, guest, callbackUrl}
 *   POST guest/checkout/tickets                     {facilityId, visitDate, lines, guest, callbackUrl}
 *   POST guest/checkout/membership                  {planId, guest, callbackUrl}
 *        -> {reference, accessToken, payment:{reference, authorizationUrl}}
 *   GET  guest/orders/{reference}                   header X-Order-Token: <accessToken>
 *   POST guest/orders/lookup                        {reference, contact}  -> {reference, accessToken}
 *   POST guest/orders/{reference}/pay               retry payment for the same order (token header)
 *   POST guest/orders/{reference}/resend            resend the ticket message
 *   POST guest/orders/{reference}/cancel            {reason}
 *
 * The `guest` object is {name, email, phone, marketingConsent, consentVersion}.
 */
class GuestCheckoutApi
{
    public const TOKEN_HEADER = 'X-Order-Token';

    public function __construct(private readonly R007ApiClient $api) {}

    private function svc(): R007ApiClient
    {
        return $this->api->asService();
    }

    /** @return array<string, mixed> */
    public function hold(string $bookingId): array
    {
        return $this->svc()->get('guest/holds/'.rawurlencode($bookingId));
    }

    /** @return array<string, mixed> */
    public function releaseHold(string $bookingId, string $key): array
    {
        return $this->svc()->post('guest/holds/'.rawurlencode($bookingId).'/release', [], $key);
    }

    /** @param array<string, mixed> $guest */
    public function payBooking(string $bookingId, array $guest, string $callbackUrl, string $key): GuestPayment
    {
        return GuestPayment::fromApi($this->svc()->post('guest/checkout/booking', [
            'bookingId' => $bookingId, 'guest' => $guest, 'callbackUrl' => $callbackUrl,
        ], $key));
    }

    /**
     * @param  list<array{productId: string, quantity: int}>  $lines
     * @param  array<string, mixed>  $guest
     */
    public function payTickets(string $facilityId, string $visitDate, array $lines, array $guest, string $callbackUrl, string $key): GuestPayment
    {
        return GuestPayment::fromApi($this->svc()->post('guest/checkout/tickets', [
            'facilityId' => $facilityId, 'visitDate' => $visitDate, 'lines' => $lines, 'guest' => $guest, 'callbackUrl' => $callbackUrl,
        ], $key));
    }

    /** @param array<string, mixed> $guest */
    public function payMembership(string $planId, array $guest, string $callbackUrl, string $key): GuestPayment
    {
        return GuestPayment::fromApi($this->svc()->post('guest/checkout/membership', [
            'planId' => $planId, 'guest' => $guest, 'callbackUrl' => $callbackUrl,
        ], $key));
    }

    /** @return array<string, mixed> */
    public function order(string $reference, string $token): array
    {
        $o = $this->svc()->get('guest/orders/'.rawurlencode($reference), [], [self::TOKEN_HEADER => $token]);

        return $o + ['reference' => $reference, 'tickets' => [], 'status' => 'PENDING_PAYMENT', 'kind' => 'tickets'];
    }

    /** @return array{reference: string, accessToken: string} */
    public function lookup(string $reference, string $contact): array
    {
        $r = $this->svc()->post('guest/orders/lookup', ['reference' => $reference, 'contact' => $contact], null);

        return ['reference' => (string) ($r['reference'] ?? $reference), 'accessToken' => (string) ($r['accessToken'] ?? '')];
    }

    public function retryPayment(string $reference, string $token, string $callbackUrl, string $key): GuestPayment
    {
        return GuestPayment::fromApi($this->svc()->post('guest/orders/'.rawurlencode($reference).'/pay', ['callbackUrl' => $callbackUrl], $key, [self::TOKEN_HEADER => $token]), $reference, $token);
    }

    /** @return array<string, mixed> {emailSent: bool, available: bool} */
    public function resend(string $reference, string $token, string $key): array
    {
        return $this->svc()->post('guest/orders/'.rawurlencode($reference).'/resend', [], $key, [self::TOKEN_HEADER => $token]);
    }

    /** @return array<string, mixed> */
    public function cancel(string $reference, string $token, string $reason, string $key): array
    {
        return $this->svc()->post('guest/orders/'.rawurlencode($reference).'/cancel', ['reason' => $reason], $key, [self::TOKEN_HEADER => $token]);
    }

    /** @return array<string, mixed> Payment (status is authoritative). */
    public function verifyPayment(string $paymentReference): array
    {
        return $this->svc()->get('payments/paystack/verify/'.rawurlencode($paymentReference));
    }
}

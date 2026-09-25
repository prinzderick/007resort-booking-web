<?php

namespace App\Services\Guest;

use App\Services\R007Api\R007ApiClient;

/**
 * Guest checkout over the 007 Resort & Spa API (contract: docs/GUEST_CHECKOUT.md in 007resort-api).
 * Every call authenticates as the website's SERVICE credential (scope public.checkout), never a customer
 * token, and forwards the visitor's IP in X-Client-IP so the API's abuse limits are per visitor.
 * No account is ever created by these calls.
 *
 *   create   POST bookings/hold | public/ticket-orders | memberships   body + `guest`  -> + guestAccess{reference, accessToken}
 *   pay      POST payments/paystack/initialize                        X-Order-Token
 *   verify   GET  payments/paystack/verify/{ref}                      X-Order-Token
 *   read     GET  public/orders/{reference}                           X-Order-Token
 *   lookup   POST public/orders/lookup {reference, email|phone}       -> order + guestAccess (fresh token)
 *   resend   POST public/orders/{reference}/resend {channel}
 *   cancel   POST bookings/{id}/cancel                                X-Order-Token, If-Match
 *   account  POST public/orders/{reference}/create-account {password, termsAccepted}
 */
class GuestCheckoutApi
{
    public const TOKEN_HEADER = 'X-Order-Token';

    public function __construct(private readonly R007ApiClient $api) {}

    private function svc(): R007ApiClient
    {
        return $this->api->asService();
    }

    /** @return array<string, string> */
    private function h(?string $token = null): array
    {
        $h = [];
        if ($token !== null) {
            $h[self::TOKEN_HEADER] = $token;
        }
        if ($ip = request()->ip()) {
            $h['X-Client-IP'] = $ip;
        }

        return $h;
    }

    /** @param array<string, mixed> $guest */
    public function startBooking(string $resourceId, string $start, string $end, int $quantity, array $guest, string $key): GuestStart
    {
        $b = $this->svc()->post('bookings/hold', ['resourceId' => $resourceId, 'start' => $start, 'end' => $end, 'quantity' => max(1, $quantity), 'guest' => $guest], $key, $this->h());

        return $this->started($b, 'bookingId', (string) ($b['id'] ?? ''), (string) ($b['total'] ?? '0'));
    }

    /**
     * @param  list<array{productId: string, quantity: int}>  $lines
     * @param  array<string, mixed>  $guest
     */
    public function startTickets(string $facilityId, string $visitDate, array $lines, array $guest, string $key): GuestStart
    {
        $o = $this->svc()->post('public/ticket-orders', ['facilityId' => $facilityId, 'visitDate' => $visitDate, 'lines' => $lines, 'guest' => $guest], $key, $this->h());

        return $this->started($o, 'orderIds', (string) ($o['id'] ?? ''), (string) ($o['total'] ?? '0'));
    }

    /** @param array<string, mixed> $guest */
    public function startMembership(string $planId, string $price, array $guest, string $key): GuestStart
    {
        $m = $this->svc()->post('memberships', ['planId' => $planId, 'guest' => $guest], $key, $this->h());

        return $this->started($m, 'membershipId', (string) ($m['id'] ?? ''), (string) ($m['price'] ?? $price));
    }

    /** @param array<string, mixed> $body */
    private function started(array $body, string $subjectKey, string $subjectId, string $amount): GuestStart
    {
        $a = (array) ($body['guestAccess'] ?? []);
        abort_if(blank($a['reference'] ?? null) || blank($a['accessToken'] ?? null) || $subjectId === '', 502);

        return new GuestStart((string) $a['reference'], (string) $a['accessToken'], $subjectKey, $subjectId, $amount);
    }

    public function pay(GuestStart|array $subject, string $token, string $callbackUrl, string $key, ?string $reference = null): GuestPayment
    {
        $s = $subject instanceof GuestStart
            ? ['key' => $subject->subjectKey, 'id' => $subject->subjectId, 'amount' => $subject->amount, 'ref' => $subject->reference]
            : $subject + ['ref' => $reference];
        $body = $this->svc()->post('payments/paystack/initialize', [
            $s['key'] => $s['key'] === 'orderIds' ? [$s['id']] : $s['id'],
            'amount' => $s['amount'],
            'callbackUrl' => $callbackUrl,
        ], $key, $this->h($token));

        return new GuestPayment((string) $s['ref'], (string) ($body['reference'] ?? ''), (string) ($body['authorizationUrl'] ?? ''));
    }

    /** Pay an existing order again (same hold / order, no duplicates). */
    public function retryPayment(string $reference, string $token, string $callbackUrl, string $key): GuestPayment
    {
        $o = $this->order($reference, $token);
        $subject = match ($o['kind']) {
            'booking' => ['key' => 'bookingId', 'id' => (string) ($o['bookingId'] ?? '')],
            'membership' => ['key' => 'membershipId', 'id' => (string) ($o['membershipId'] ?? '')],
            default => ['key' => 'orderIds', 'id' => (string) ($o['ticketOrderId'] ?? '')],
        };
        abort_if($subject['id'] === '', 502);

        return $this->pay($subject + ['amount' => (string) $o['total']], $token, $callbackUrl, $key, $reference);
    }

    /** @return array<string, mixed> Payment (status is authoritative). */
    public function verifyPayment(string $paymentReference, string $token): array
    {
        return $this->svc()->get('payments/paystack/verify/'.rawurlencode($paymentReference), [], $this->h($token));
    }

    /** @return array<string, mixed> the site's own view model of an order */
    public function order(string $reference, string $token): array
    {
        return self::present($this->svc()->get('public/orders/'.rawurlencode($reference), [], $this->h($token)));
    }

    /** @return array{reference: string, accessToken: string} */
    public function lookup(string $reference, string $contact): array
    {
        $r = $this->svc()->post('public/orders/lookup', ['reference' => $reference, str_contains($contact, '@') ? 'email' : 'phone' => $contact], null, $this->h());
        $a = (array) ($r['guestAccess'] ?? []);

        return ['reference' => (string) ($a['reference'] ?? $r['reference'] ?? $reference), 'accessToken' => (string) ($a['accessToken'] ?? '')];
    }

    /** @return array<string, mixed> */
    public function resend(string $reference, string $token, string $key): array
    {
        return $this->svc()->post('public/orders/'.rawurlencode($reference).'/resend', ['channel' => 'EMAIL'], $key, $this->h($token));
    }

    /** @param array<string, mixed> $order presented order */
    public function cancel(array $order, string $token, string $reason, string $key): array
    {
        $id = (string) ($order['bookingId'] ?? '');
        abort_if($id === '', 404);
        $h = $this->h($token) + (isset($order['rowVersion']) ? ['If-Match' => '"'.$order['rowVersion'].'"'] : []);

        return $this->svc()->post("bookings/{$id}/cancel", ['reason' => $reason], $key, $h);
    }

    public function createAccount(string $reference, string $token, string $password, string $key): void
    {
        $this->svc()->post('public/orders/'.rawurlencode($reference).'/create-account', ['password' => $password, 'termsAccepted' => true], $key, $this->h($token));
    }

    /**
     * Map the API's guest order view onto the flat shape the pages use.
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    public static function present(array $o): array
    {
        $kind = strtolower((string) ($o['kind'] ?? 'tickets'));
        $b = (array) ($o['booking'] ?? []);
        $t = (array) ($o['ticketOrder'] ?? []);
        $m = (array) ($o['membership'] ?? []);
        $raw = strtoupper((string) ($o['status'] ?? ''));
        $paid = (bool) ($o['paid'] ?? false);
        $status = match (true) {
            $paid || in_array($raw, ['CONFIRMED', 'COMPLETED', 'RESCHEDULED', 'PAID', 'ACTIVE'], true) => 'PAID',
            in_array($raw, ['CANCELLED', 'EXPIRED', 'REFUNDED', 'FAILED'], true) => $raw,
            default => 'PENDING_PAYMENT',
        };

        $tickets = [];
        foreach ((array) ($o['tickets'] ?? []) as $e) {
            $item = (array) (($e['items'] ?? [])[0] ?? []);
            $tickets[] = [
                'id' => (string) ($e['id'] ?? ''), 'qrToken' => (string) ($e['qrToken'] ?? ''), 'status' => (string) ($e['status'] ?? 'ACTIVE'),
                'name' => $item['name'] ?? ($b['resourceName'] ?? 'Ticket'), 'holderName' => $e['holderName'] ?? null,
                'validFrom' => $item['validFrom'] ?? null, 'validUntil' => $item['validUntil'] ?? null,
            ];
        }
        // Memberships carry their card(s) on the membership itself.
        if ($tickets === [] && $kind === 'membership' && $status === 'PAID') {
            foreach ((array) ($m['cards'] ?? []) as $c) {
                $tickets[] = ['id' => (string) ($c['id'] ?? ''), 'qrToken' => (string) ($c['qrToken'] ?? ''), 'status' => 'ACTIVE', 'name' => ($m['planName'] ?? 'Membership').' membership', 'holderName' => $m['holderName'] ?? null, 'validFrom' => $m['validFrom'] ?? null, 'validUntil' => $m['validUntil'] ?? null];
            }
        }

        return [
            'reference' => (string) ($o['reference'] ?? ''), 'kind' => $kind, 'status' => $status,
            'total' => (string) ($b['total'] ?? $t['total'] ?? $m['price'] ?? $o['amountDue'] ?? '0'),
            'title' => $kind === 'booking' ? ($b['resourceName'] ?? 'Booking') : ($kind === 'membership' ? (($m['planName'] ?? 'Membership').' membership') : 'Pool day passes'),
            'start' => $b['start'] ?? $m['validFrom'] ?? null, 'end' => $b['end'] ?? $m['validUntil'] ?? null, 'visitDate' => $t['visitDate'] ?? null,
            'quantity' => $b['quantity'] ?? count($tickets) ?: 1, 'facilityId' => $b['facilityId'] ?? $t['facilityId'] ?? null,
            'guest' => (array) ($o['contact'] ?? []),
            'holdExpiresAt' => in_array($status, ['PENDING_PAYMENT'], true) ? ($b['holdExpiresAt'] ?? null) : null,
            'policy' => (array) ($b['policy'] ?? []),
            'bookingId' => $b['id'] ?? null, 'rowVersion' => $b['rowVersion'] ?? null, 'ticketOrderId' => $t['id'] ?? null, 'membershipId' => $m['id'] ?? null,
            'tickets' => $tickets,
            // The API does not report delivery yet; until it does, only claim an email if the site is told mail works.
            'delivery' => (array) ($o['delivery'] ?? []) + ['available' => (bool) config('r007.checkout.email_delivery'), 'emailSent' => false],
        ];
    }
}

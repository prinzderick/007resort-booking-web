<?php

namespace App\Services\R007Api;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * In-process stand-in for the API (R007_MOCK=true) so the site runs without the
 * backend. It mimics the contract (and the PROPOSED customer/public endpoints in
 * docs/API_DEPENDENCIES.md) closely enough to exercise every UX state:
 * slot_unavailable, hold expiry, Paystack success/failure, degraded facility.
 *
 * Deliberately simple: this is NOT booking logic for production, only a fixture.
 */
class MockR007ApiClient extends R007ApiClient
{
    private const FAC = [
        'sports' => '0192f6a0-0000-7000-8000-000000000101',
        'spa' => '0192f6a0-0000-7000-8000-000000000102',
        'salon' => '0192f6a0-0000-7000-8000-000000000103',
        'pool' => '0192f6a0-0000-7000-8000-000000000104',
        'restaurant' => '0192f6a0-0000-7000-8000-000000000105',
        'cafe' => '0192f6a0-0000-7000-8000-000000000106',
        'supermarket' => '0192f6a0-0000-7000-8000-000000000107',
        'bar' => '0192f6a0-0000-7000-8000-000000000108',
        'club' => '0192f6a0-0000-7000-8000-000000000109',
    ];

    /** @var array<string, mixed> */
    private array $s = [];

    /** @var array<string, string> */
    private array $reqHeaders = [];

    protected function send(string $method, string $path, array $options, ?string $idempotencyKey = null, array $headers = []): array
    {
        $this->s = MockState::get();
        $this->s['gorders'] ??= [];
        $this->reqHeaders = $headers;
        $path = trim($path, '/');

        $replayKey = $method !== 'GET' && $idempotencyKey ? $method.' '.$path.' '.$idempotencyKey : null;
        if ($replayKey !== null && isset($this->s['idem'][$replayKey])) {
            return $this->s['idem'][$replayKey];
        }

        $result = $this->route($method, $path, (array) ($options['query'] ?? []), (array) ($options['json'] ?? []));

        if ($replayKey !== null) {
            $this->s['idem'][$replayKey] = $result;
        }
        MockState::put($this->s);

        return $result;
    }

    /** @return array<mixed> */
    private function route(string $m, string $p, array $q, array $j): array
    {
        $r = "$m $p";

        return match (true) {
            $r === 'GET public/site' => $this->site(),
            $r === 'POST customer/auth/register' => $this->register($j),
            $r === 'POST customer/auth/login' => $this->login($j),
            $r === 'POST customer/auth/verify' => $this->verify($j),
            $r === 'POST customer/auth/verify/resend' => [],
            $r === 'POST customer/auth/logout' => [],
            $r === 'GET customer/me' => $this->me(),
            $r === 'GET bookings/resources' => $this->page($this->resources($q['facilityId'] ?? null)),
            (bool) preg_match('#^GET bookings/resources/([^/]+)/availability$#', $r, $x) => $this->availability($x[1], $q),
            $r === 'POST bookings/hold' => $this->hold($j),
            (bool) preg_match('#^GET bookings/([^/]+)$#', $r, $x) => $this->getBooking($x[1]),
            (bool) preg_match('#^POST bookings/([^/]+)/confirm$#', $r, $x) => $this->confirm($x[1], $j),
            (bool) preg_match('#^POST bookings/([^/]+)/cancel$#', $r, $x) => $this->cancel($x[1]),
            (bool) preg_match('#^POST bookings/([^/]+)/reschedule$#', $r, $x) => $this->reschedule($x[1], $j),
            $r === 'GET customer/bookings' => $this->page($this->myBookings()),
            (bool) preg_match('#^GET entitlements/([^/]+)$#', $r, $x) => $this->entitlement($x[1]),
            $r === 'GET customer/entitlements' => $this->page($this->entitlementsFor($q)),
            $r === 'GET catalog/products' => $this->page($this->ticketProducts()),
            $r === 'POST public/ticket-orders' => $this->ticketOrder($j),
            (bool) preg_match('#^GET customer/orders/([^/]+)$#', $r, $x) => $this->s['orders'][$x[1]] ?? $this->problem(404, 'not_found', 'Not found'),
            $r === 'GET memberships/plans' => $this->page($this->plans()),
            $r === 'POST memberships' => $this->startMembership($j),
            $r === 'GET customer/memberships' => $this->page(array_values(array_filter($this->s['memberships'], fn ($x) => $x['customerId'] === $this->customerId()))),
            (bool) preg_match('#^GET memberships/([^/]+)$#', $r, $x) => $this->s['memberships'][$x[1]] ?? $this->problem(404, 'not_found', 'Not found'),
            $r === 'POST payments/paystack/initialize' => $this->paystackInit($j),
            (bool) preg_match('#^GET payments/paystack/verify/(.+)$#', $r, $x) => $this->s['payments'][rawurldecode($x[1])] ?? $this->problem(404, 'not_found', 'Unknown payment reference'),
            $r === 'POST public/orders/lookup' => $this->guestLookup($j),
            (bool) preg_match('#^GET public/orders/([^/]+)$#', $r, $x) => $this->guestOrderView($this->guestOrder($x[1])),
            (bool) preg_match('#^POST public/orders/([^/]+)/resend$#', $r, $x) => $this->guestResend($this->guestOrder($x[1])),
            (bool) preg_match('#^POST public/orders/([^/]+)/create-account$#', $r, $x) => $this->guestCreateAccount($this->guestOrder($x[1]), $j),
            default => $this->problem(404, 'not_found', 'Mock API: no such endpoint', $r),
        };
    }

    // ---- site & catalogue ------------------------------------------------

    private function site(): array
    {
        $off = array_filter(array_map('trim', explode(',', (string) env('R007_MOCK_OFFLINE_FACILITIES', ''))));
        $mk = fn (string $key, string $kind, string $name, string $hours) => [
            'id' => self::FAC[$key], 'code' => strtoupper($key), 'kind' => $kind, 'name' => $name, 'openingHours' => $hours,
            'onlineBookable' => ! in_array($key, $off, true),
            'onlineNotice' => in_array($key, $off, true) ? 'Online booking is paused while our on-site systems reconnect. Please call reception.' : null,
        ];

        return [
            'contact' => ['phone' => '+234 800 007 0007', 'email' => 'hello@007resort.example', 'address' => '007 Resort & Spa, near the Federal University Otueke, Ogbia, Bayelsa State', 'mapUrl' => null],
            'openingHours' => 'Daily, 07:00 - 23:00',
            'facilities' => [
                $mk('sports', 'SPORTS_ARENA', 'Sports Arena', 'Daily, 07:00 - 21:00'),
                $mk('spa', 'SPA', 'Beauty Spa', 'Daily, 09:00 - 20:00'),
                $mk('salon', 'SALON', 'Salon', 'Mon-Sat, 09:00 - 19:00'),
                $mk('pool', 'POOL', 'Swimming Pool', 'Daily, 09:00 - 19:00'),
                $mk('restaurant', 'RESTAURANT', 'Restaurant', 'Daily, 07:00 - 22:00'),
                $mk('cafe', 'CAFE', 'Cafe', 'Daily, 07:00 - 18:00'),
                $mk('supermarket', 'SUPERMARKET', 'Supermarket', 'Daily, 08:00 - 21:00'),
                $mk('bar', 'BUSH_BAR', 'Bush Bar & Event Centre', 'Daily, 12:00 - 00:00'),
                $mk('club', 'INDOOR_CLUB', 'Indoor Club', 'Daily, 16:00 - 00:00'),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function resources(?string $facilityId): array
    {
        $mk = fn (string $n, string $fac, string $name, int $mins, string $price, string $mode = 'TIME_SLOT', int $cap = 1) => [
            'id' => '0192f6a0-0000-7000-8000-0000000002'.$n, 'facilityId' => self::FAC[$fac], 'name' => $name, 'mode' => $mode,
            'capacity' => $cap, 'slotMinutes' => $mins, 'price' => $price, 'active' => true,
        ];
        $all = [
            $mk('01', 'sports', 'Lawn Tennis Court 1', 60, '5000.0000'),
            $mk('02', 'sports', 'Lawn Tennis Court 2', 60, '5000.0000'),
            $mk('03', 'sports', 'Football Pitch', 60, '25000.0000', 'WHOLE_RESOURCE'),
            $mk('04', 'spa', 'Swedish Massage (60 min)', 60, '25000.0000'),
            $mk('05', 'spa', 'Signature Facial (45 min)', 45, '18000.0000'),
            $mk('06', 'salon', 'Knotless Braids', 120, '15000.0000'),
            $mk('07', 'salon', 'Haircut & Style', 30, '5000.0000'),
        ];

        return array_values(array_filter($all, fn ($r) => $facilityId === null || $r['facilityId'] === $facilityId));
    }

    private function resource(string $id): array
    {
        foreach ($this->resources(null) as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        $this->problem(404, 'not_found', 'Resource not found');
    }

    private function availability(string $id, array $q): array
    {
        $r = $this->resource($id);
        if (in_array($r['facilityId'], $this->offFacilityIds(), true)) {
            $this->problem(409, 'capability_disabled', 'Online booking unavailable', 'Site heartbeat is stale for this resource.');
        }
        $from = CarbonImmutable::parse($q['from'])->setTimezone(config('r007.display_timezone'));
        $slots = [];
        for ($t = $from->setTime(8, 0); $t->lt($from->setTime(20, 0)); $t = $t->addMinutes($r['slotMinutes'])) {
            $end = $t->addMinutes($r['slotMinutes']);
            $taken = $this->isTaken($id, $t) || $t->isPast() || crc32($id.$t->format('c')) % 7 === 0;
            $slots[] = [
                'start' => $t->utc()->format('Y-m-d\TH:i:s\Z'), 'end' => $end->utc()->format('Y-m-d\TH:i:s\Z'),
                'available' => ! $taken, 'remainingCapacity' => $taken ? 0 : $r['capacity'], 'price' => $r['price'],
            ];
        }

        return ['resourceId' => $id, 'slots' => $slots];
    }

    /** @return list<string> */
    private function offFacilityIds(): array
    {
        $off = array_filter(array_map('trim', explode(',', (string) env('R007_MOCK_OFFLINE_FACILITIES', ''))));

        return array_map(fn ($k) => self::FAC[$k] ?? '', $off);
    }

    private function isTaken(string $resourceId, CarbonImmutable $start, ?string $exceptBooking = null): bool
    {
        foreach ($this->s['bookings'] as $b) {
            if ($b['resourceId'] !== $resourceId || $b['id'] === $exceptBooking) {
                continue;
            }
            $live = in_array($b['status'], ['CONFIRMED', 'PENDING_PAYMENT'], true)
                || ($b['status'] === 'HELD' && CarbonImmutable::parse($b['holdExpiresAt'])->isFuture());
            if ($live && CarbonImmutable::parse($b['start'])->equalTo($start)) {
                return true;
            }
        }

        return false;
    }

    // ---- customers -------------------------------------------------------

    private function register(array $j): array
    {
        foreach ($this->s['customers'] as $c) {
            if (strtolower($c['email']) === strtolower($j['email'])) {
                $this->problem(422, 'validation_failed', 'Validation failed', null, ['email' => ['This email is already registered.']]);
            }
        }
        $id = (string) Str::uuid();
        $this->s['customers'][$id] = ['id' => $id, 'name' => $j['name'], 'email' => $j['email'], 'phone' => $j['phone'], 'password' => $j['password'], 'verified' => false];
        MockState::put($this->s);

        return ['customerId' => $id, 'verificationRequired' => true];
    }

    private function findByEmail(string $email): ?array
    {
        foreach ($this->s['customers'] as $c) {
            if (strtolower($c['email']) === strtolower($email)) {
                return $c;
            }
        }

        return null;
    }

    private function session(array $c): array
    {
        $token = 'mock-'.Str::random(32);
        $this->s['tokens'][$token] = $c['id'];
        MockState::put($this->s);

        return ['accessToken' => $token, 'customer' => $this->profile($c)];
    }

    private function profile(array $c): array
    {
        return ['id' => $c['id'], 'name' => $c['name'], 'email' => $c['email'], 'phone' => $c['phone'], 'emailVerified' => $c['verified']];
    }

    private function login(array $j): array
    {
        $c = $this->findByEmail($j['email']);
        if (! $c || $c['password'] !== $j['password']) {
            $this->problem(401, 'invalid_credentials', 'Invalid credentials');
        }
        if (! $c['verified']) {
            $this->problem(403, 'permission_denied', 'Email not verified', 'Please verify your email address first.');
        }

        return $this->session($c);
    }

    private function verify(array $j): array
    {
        $c = $this->findByEmail($j['email']);
        if (! $c || $j['code'] !== '123456') {
            $this->problem(422, 'validation_failed', 'Invalid code', 'That code is not valid. (Mock mode: use 123456.)');
        }
        $this->s['customers'][$c['id']]['verified'] = true;
        $c['verified'] = true;

        return $this->session($c);
    }

    private function customerId(): ?string
    {
        $t = $this->token();

        return $t ? ($this->s['tokens'][$t] ?? null) : null;
    }

    private function requireCustomer(): string
    {
        return $this->customerId() ?? $this->problem(401, 'unauthenticated', 'Sign in required');
    }

    private function me(): array
    {
        return $this->profile($this->s['customers'][$this->requireCustomer()]);
    }

    // ---- bookings --------------------------------------------------------

    private function hold(array $j): array
    {
        $guest = isset($j['guest']) ? $this->guestIdentity($j) : null;
        $cid = $guest ? 'guest' : $this->requireCustomer();
        $r = $this->resource($j['resourceId']);
        $start = CarbonImmutable::parse($j['start']);
        if ($this->isTaken($r['id'], $start) || crc32($r['id'].$start->setTimezone(config('r007.display_timezone'))->format('c')) % 7 === 0) {
            $this->problem(409, 'slot_unavailable', 'Slot unavailable', 'That slot has just been taken.');
        }
        $id = (string) Str::uuid();
        $seq = ++$this->s['seq'];
        $qty = (int) ($j['quantity'] ?? 1);
        $this->s['bookings'][$id] = [
            'id' => $id, 'number' => sprintf('BK-%s-%04d', now()->format('Ymd'), $seq), 'resourceId' => $r['id'], 'resourceName' => $r['name'],
            'facilityId' => $r['facilityId'], 'start' => $j['start'], 'end' => $j['end'], 'quantity' => $qty, 'status' => 'HELD',
            'holdExpiresAt' => now()->addSeconds(600)->utc()->format('Y-m-d\TH:i:s\Z'), 'customer' => $j['customer'] ?? [],
            'total' => $this->mul($r['price'], $qty), 'amountPaid' => '0.0000', 'orderId' => null, 'entitlementId' => null,
            'source' => 'ONLINE', 'rowVersion' => 1, 'createdAt' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'customerId' => $cid,
        ];
        MockState::put($this->s);
        $out = $this->publicBooking($this->s['bookings'][$id]);
        if ($guest) {
            $out['guestAccess'] = $this->newGuestOrder('BOOKING', ['bookingId' => $id], $guest);
        }

        return $out;
    }

    private function loadBooking(string $id): array
    {
        $b = $this->s['bookings'][$id] ?? null;
        if (! $b || $b['customerId'] !== $this->requireCustomer()) {
            $this->problem(404, 'not_found', 'Booking not found');
        }
        if ($b['status'] === 'HELD' && CarbonImmutable::parse($b['holdExpiresAt'])->isPast()) {
            $b['status'] = 'EXPIRED';
            $this->s['bookings'][$id] = $b;
        }

        return $b;
    }

    private function getBooking(string $id): array
    {
        return $this->publicBooking($this->loadBooking($id));
    }

    private function publicBooking(array $b): array
    {
        $start = CarbonImmutable::parse($b['start']);
        $canChange = $b['status'] === 'CONFIRMED' && $start->gt(now()->addHours(24));
        unset($b['customerId']);
        $b['policy'] = [
            'canCancel' => $canChange, 'canReschedule' => $canChange, 'cancelBy' => $start->subHours(24)->utc()->format('Y-m-d\TH:i:s\Z'),
            'refundAmount' => $canChange ? $b['amountPaid'] : '0.0000', 'note' => 'Free cancellation or rescheduling up to 24 hours before start.',
        ];

        return $b;
    }

    private function checkVersion(array $b): void
    {
        $im = $this->reqHeaders['If-Match'] ?? null;
        if ($im !== null && $im !== '"'.$b['rowVersion'].'"') {
            $this->problem(412, 'concurrency_conflict', 'Booking changed', 'Reload and try again.');
        }
    }

    private function confirm(string $id, array $j): array
    {
        $b = $this->loadBooking($id);
        $this->checkVersion($b);
        $pay = $this->s['payments'][$j['paystackReference'] ?? ''] ?? null;
        if (! $pay || $pay['status'] !== 'CAPTURED') {
            $this->problem(409, 'payment_state_invalid', 'Payment not captured');
        }

        return $this->publicBooking($this->applyBookingPaid($id));
    }

    private function applyBookingPaid(string $id): array
    {
        $b = $this->s['bookings'][$id];
        if ($b['status'] === 'CONFIRMED') {
            return $b;
        }
        if ($b['status'] === 'EXPIRED') {
            return $b;
        }
        $eid = (string) Str::uuid();
        $b['status'] = 'CONFIRMED';
        $b['amountPaid'] = $b['total'];
        $b['entitlementId'] = $eid;
        $b['rowVersion']++;
        $this->s['entitlements'][$eid] = $this->makeEntitlement($eid, $b['resourceName'].' - '.CarbonImmutable::parse($b['start'])->setTimezone(config('r007.display_timezone'))->format('D j M, H:i'), $b['facilityId'], $b['start'], $b['end'], $b['customer']['name'] ?? null, $b['customerId'], ['bookingId' => $id]);
        $this->s['bookings'][$id] = $b;

        return $b;
    }

    private function cancel(string $id): array
    {
        $b = $this->tokenBooking($id) ?? $this->loadBooking($id);
        $this->checkVersion($b);
        if ($b['status'] === 'CONFIRMED' && CarbonImmutable::parse($b['start'])->lte(now()->addHours(24))) {
            $this->problem(409, 'order_state_invalid', 'Cannot cancel', 'The cancellation window closed 24 hours before the start time.');
        }
        if (! in_array($b['status'], ['HELD', 'PENDING_PAYMENT', 'CONFIRMED'], true)) {
            $this->problem(409, 'order_state_invalid', 'Cannot cancel', 'This booking can no longer be cancelled.');
        }
        $b['status'] = 'CANCELLED';
        $b['rowVersion']++;
        $this->s['bookings'][$id] = $b;

        return $this->publicBooking($b);
    }

    private function reschedule(string $id, array $j): array
    {
        $b = $this->loadBooking($id);
        $this->checkVersion($b);
        $start = CarbonImmutable::parse($j['start']);
        if ($b['status'] !== 'CONFIRMED' || CarbonImmutable::parse($b['start'])->lte(now()->addHours(24))) {
            $this->problem(409, 'order_state_invalid', 'Cannot reschedule', 'Rescheduling closed 24 hours before the start time.');
        }
        if ($this->isTaken($b['resourceId'], $start, $id)) {
            $this->problem(409, 'slot_unavailable', 'Slot unavailable');
        }
        $b['start'] = $j['start'];
        $b['end'] = $j['end'];
        $b['rowVersion']++;
        $this->s['bookings'][$id] = $b;

        return $this->publicBooking($b);
    }

    /** @return list<array<string, mixed>> */
    private function myBookings(): array
    {
        $cid = $this->requireCustomer();
        $rows = array_filter($this->s['bookings'], fn ($b) => $b['customerId'] === $cid);
        usort($rows, fn ($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

        return array_map(fn ($b) => $this->publicBooking($this->loadBooking($b['id'])), array_values($rows));
    }

    // ---- entitlements, tickets, memberships -----------------------------

    private function makeEntitlement(string $id, string $name, string $facilityId, ?string $from, ?string $until, ?string $holder, string $customerId, array $source): array
    {
        return $source + [
            'id' => $id, 'qrToken' => 'mock.'.rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '='), 'status' => 'ACTIVE',
            'orderId' => $source['orderId'] ?? null, 'bookingId' => $source['bookingId'] ?? null, 'holderName' => $holder, 'customerId' => $customerId,
            'items' => [[
                'id' => (string) Str::uuid(), 'kind' => 'ACCESS', 'name' => $name, 'facilityId' => $facilityId, 'quantity' => 1, 'quantityRedeemed' => 0,
                'validationMode' => 'SINGLE_USE', 'validFrom' => $from, 'validUntil' => $until, 'rentalStatus' => null,
            ]],
            'issuedAt' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    private function entitlement(string $id): array
    {
        $e = $this->s['entitlements'][$id] ?? null;
        if (! $e || $e['customerId'] !== $this->requireCustomer()) {
            $this->problem(404, 'not_found', 'Ticket not found');
        }
        unset($e['customerId']);

        return $e;
    }

    /** @return list<array<string, mixed>> */
    private function entitlementsFor(array $q): array
    {
        $cid = $this->requireCustomer();
        $out = [];
        foreach ($this->s['entitlements'] as $e) {
            if ($e['customerId'] === $cid && (($q['orderId'] ?? null) === null || $e['orderId'] === $q['orderId']) && (($q['bookingId'] ?? null) === null || $e['bookingId'] === $q['bookingId'])) {
                unset($e['customerId']);
                $out[] = $e;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function ticketProducts(): array
    {
        return [
            ['id' => '0192f6a0-0000-7000-8000-000000000301', 'name' => 'Pool day ticket - Adult', 'kind' => 'TICKET', 'price' => '3000.0000', 'currency' => 'NGN', 'active' => true, 'ticketCategory' => 'ADULT'],
            ['id' => '0192f6a0-0000-7000-8000-000000000302', 'name' => 'Pool day ticket - Child', 'kind' => 'TICKET', 'price' => '1500.0000', 'currency' => 'NGN', 'active' => true, 'ticketCategory' => 'CHILD'],
        ];
    }

    private function ticketOrder(array $j): array
    {
        $guest = isset($j['guest']) ? $this->guestIdentity($j) : null;
        $cid = $guest ? 'guest' : $this->requireCustomer();
        $id = (string) Str::uuid();
        $prices = array_column($this->ticketProducts(), 'price', 'id');
        $names = array_column($this->ticketProducts(), 'name', 'id');
        $total = '0.0000';
        $tickets = [];
        foreach ($j['lines'] as $l) {
            $price = $prices[$l['productId']] ?? $this->problem(422, 'validation_failed', 'Unknown ticket type');
            $total = $this->add($total, $this->mul($price, $l['quantity']));
            for ($i = 0; $i < $l['quantity']; $i++) {
                $tickets[] = $names[$l['productId']];
            }
        }
        $this->s['orders'][$id] = ['id' => $id, 'status' => 'PENDING_PAYMENT', 'total' => $total, 'visitDate' => $j['visitDate'], 'tickets' => $tickets, 'customerId' => $cid, 'facilityId' => $j['facilityId']];
        MockState::put($this->s);

        $out = ['id' => $id, 'status' => 'PENDING_PAYMENT', 'total' => $total, 'visitDate' => $j['visitDate']];
        if ($guest) {
            $out['guestAccess'] = $this->newGuestOrder('TICKETS', ['orderIds' => [$id]], $guest);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function plans(): array
    {
        return [
            ['id' => '0192f6a0-0000-7000-8000-000000000401', 'name' => 'Silver Monthly', 'durationDays' => 30, 'price' => '30000.0000', 'currency' => 'NGN', 'facilityIds' => [], 'visitLimit' => 12, 'active' => true],
            ['id' => '0192f6a0-0000-7000-8000-000000000402', 'name' => 'Gold Quarterly', 'durationDays' => 90, 'price' => '75000.0000', 'currency' => 'NGN', 'facilityIds' => [], 'visitLimit' => null, 'active' => true],
        ];
    }

    private function startMembership(array $j): array
    {
        $guest = isset($j['guest']) ? $this->guestIdentity($j) : null;
        $cid = $guest ? 'guest' : $this->requireCustomer();
        $plan = collect($this->plans())->firstWhere('id', $j['planId']) ?? $this->problem(404, 'not_found', 'Plan not found');
        $id = (string) Str::uuid();
        $this->s['memberships'][$id] = [
            'id' => $id, 'number' => 'M-'.strtoupper(Str::random(6)), 'planId' => $plan['id'], 'planName' => $plan['name'], 'holderName' => $guest['name'] ?? $j['customer']['name'] ?? '',
            'status' => 'PENDING_PAYMENT', 'validFrom' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'validUntil' => now()->addDays($plan['durationDays'])->utc()->format('Y-m-d\TH:i:s\Z'),
            'visitsUsed' => 0, 'visitLimit' => $plan['visitLimit'], 'qrToken' => 'mock.m.'.Str::random(20), 'customerId' => $cid, 'price' => $plan['price'],
        ];
        MockState::put($this->s);
        $out = $this->s['memberships'][$id];
        if ($guest) {
            $out['guestAccess'] = $this->newGuestOrder('MEMBERSHIP', ['membershipId' => $id], $guest);
        }

        return $out;
    }

    // ---- payments --------------------------------------------------------

    private function paystackInit(array $j): array
    {
        $g = null;
        if (($tok = $this->reqHeaders['X-Order-Token'] ?? null) !== null) {
            foreach ($this->s['gorders'] as $o) {
                if (hash_equals($o['token'], (string) $tok)) {
                    $g = $o;
                }
            }
            $g ?? $this->problem(401, 'order_token_invalid', 'Invalid order token');
        } else {
            $this->requireCustomer();
        }
        $expected = match (true) {
            isset($j['bookingId']) => $this->s['bookings'][$j['bookingId']]['total'] ?? null,
            isset($j['orderIds']) => $this->s['orders'][$j['orderIds'][0]]['total'] ?? null,
            isset($j['membershipId']) => $this->s['memberships'][$j['membershipId']]['price'] ?? null,
            default => null,
        };
        if ($expected === null) {
            $this->problem(404, 'not_found', 'Payment subject not found');
        }
        if ($this->minor($expected) !== $this->minor($j['amount'])) {
            $this->problem(409, 'amount_mismatch', 'Amount mismatch');
        }
        if (isset($j['bookingId']) && $this->s['bookings'][$j['bookingId']]['status'] === 'HELD' && CarbonImmutable::parse($this->s['bookings'][$j['bookingId']]['holdExpiresAt'])->isPast()) {
            $this->problem(409, 'hold_expired', 'Hold expired');
        }
        $ref = 'R007-'.strtoupper(Str::random(12));
        $id = (string) Str::uuid();
        $this->s['payments'][$ref] = [
            'id' => $id, 'reference' => $ref, 'providerReference' => $ref, 'provider' => 'PAYSTACK', 'status' => 'AUTHORIZING', 'amount' => $j['amount'],
            'currency' => 'NGN', 'subject' => array_intersect_key($j, array_flip(['bookingId', 'orderIds', 'membershipId'])) + ($g ? ['guestOrder' => $g['reference']] : []), 'callbackUrl' => $j['callbackUrl'] ?? null,
        ];
        if ($g) {
            $this->s['gorders'][$g['reference']]['payments'][] = $ref;
        }
        MockState::put($this->s);

        return ['paymentId' => $id, 'reference' => $ref, 'authorizationUrl' => url('/mock/paystack/'.$ref), 'accessCode' => 'mock'];
    }

    /** Public so the mock Paystack page can simulate the provider. */
    public function settle(string $reference, string $status): ?array
    {
        $this->s = MockState::get();
        $this->s['gorders'] ??= [];
        $p = $this->s['payments'][$reference] ?? null;
        if ($p === null) {
            return null;
        }
        $p['status'] = $status;
        $this->s['payments'][$reference] = $p;
        if ($status === 'CAPTURED') {
            $sub = $p['subject'];
            if (isset($sub['guestOrder'])) {
                MockState::put($this->s);
                $this->settleGuestOrder($reference);

                return $p;
            }
            if (isset($sub['orderIds'])) {
                $o = $this->s['orders'][$sub['orderIds'][0]];
                if ($o['status'] !== 'PAID') {
                    $o['status'] = 'PAID';
                    $visit = CarbonImmutable::parse($o['visitDate'], config('r007.display_timezone'));
                    foreach ($o['tickets'] as $name) {
                        $eid = (string) Str::uuid();
                        $this->s['entitlements'][$eid] = $this->makeEntitlement($eid, $name, $o['facilityId'], $visit->utc()->format('Y-m-d\TH:i:s\Z'), $visit->endOfDay()->utc()->format('Y-m-d\TH:i:s\Z'), null, $o['customerId'], ['orderId' => $o['id']]);
                    }
                    $this->s['orders'][$o['id']] = $o;
                }
            } elseif (isset($sub['membershipId'])) {
                $this->s['memberships'][$sub['membershipId']]['status'] = 'ACTIVE';
            }
            // Booking payments are NOT auto-confirmed here on purpose: the site's
            // confirm-after-verify fallback is exercised in mock mode too.
        }
        MockState::put($this->s);

        return $p;
    }

    // ---- guest checkout (no account): contract docs/GUEST_CHECKOUT.md -----

    /** @return array<string, string> */
    private function guestIdentity(array $j): array
    {
        $g = (array) ($j['guest'] ?? []);
        $errors = [];
        foreach (['name', 'email', 'phone'] as $f) {
            if (blank($g[$f] ?? null)) {
                $errors["guest.$f"] = ['Required.'];
            }
        }
        if ($errors) {
            $this->problem(422, 'validation_failed', 'Validation failed', null, $errors);
        }

        return ['name' => $g['name'], 'email' => strtolower($g['email']), 'phone' => $g['phone']];
    }

    /** @return array<string, mixed> */
    private function newGuestOrder(string $kind, array $subject, array $guest): array
    {
        $ref = 'GC-'.strtoupper(Str::random(8));
        $token = 'r7o_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->s['gorders'][$ref] = ['reference' => $ref, 'token' => $token, 'kind' => $kind, 'subject' => $subject, 'guest' => $guest, 'payments' => [], 'createdAt' => now()->utc()->format('Y-m-d\TH:i:s\Z')];
        MockState::put($this->s);

        return ['reference' => $ref, 'accessToken' => $token, 'accessTokenExpiresAt' => now()->addDays(90)->utc()->format('Y-m-d\TH:i:s\Z'), 'contact' => $guest];
    }

    private function guestOrder(string $ref): array
    {
        $o = $this->s['gorders'][$ref] ?? null;
        $presented = (string) ($this->reqHeaders['X-Order-Token'] ?? '');
        if ($presented === '') {
            $this->problem(401, 'order_token_invalid', 'Invalid order token');
        }
        if (! $o || ! hash_equals($o['token'], $presented)) {
            $this->problem(404, 'not_found', 'Not found');
        }

        return $o;
    }

    private function tokenBooking(string $id): ?array
    {
        $tok = (string) ($this->reqHeaders['X-Order-Token'] ?? '');
        if ($tok === '') {
            return null;
        }
        foreach ($this->s['gorders'] as $o) {
            if (($o['subject']['bookingId'] ?? null) === $id && hash_equals($o['token'], $tok)) {
                return $this->expireHold($this->s['bookings'][$id]);
            }
        }
        $this->problem(404, 'not_found', 'Not found');
    }

    private function expireHold(array $b): array
    {
        if ($b['status'] === 'HELD' && CarbonImmutable::parse($b['holdExpiresAt'])->isPast()) {
            $b['status'] = 'EXPIRED';
            $this->s['bookings'][$b['id']] = $b;
        }

        return $b;
    }

    private function guestLookup(array $j): array
    {
        $o = $this->s['gorders'][strtoupper((string) ($j['reference'] ?? ''))] ?? null;
        $email = strtolower(trim((string) ($j['email'] ?? '')));
        $digits = preg_replace('/\D/', '', (string) ($j['phone'] ?? ''));
        $ok = $o && (
            ($email !== '' && $email === strtolower($o['guest']['email']))
            || ($digits !== '' && str_ends_with(preg_replace('/\D/', '', $o['guest']['phone']), ltrim($digits, '0')))
        );
        if (! $ok) {
            $this->problem(404, 'order_not_found', "We couldn't find an order with those details.");
        }

        return $this->guestOrderView($o) + ['guestAccess' => ['reference' => $o['reference'], 'accessToken' => $o['token']]];
    }

    private function settleGuestOrder(string $paymentReference): void
    {
        foreach ($this->s['gorders'] as $ref => $o) {
            if (! in_array($paymentReference, $o['payments'], true) || ! empty($o['paid'])) {
                continue;
            }
            $guest = $o['guest'];
            $sub = $o['subject'];
            if (isset($sub['bookingId'])) {
                $b = $this->expireHold($this->s['bookings'][$sub['bookingId']]);
                if ($b['status'] === 'EXPIRED') {
                    continue; // paid after the hold ended: finance handles it
                }
                $eid = (string) Str::uuid();
                $b['status'] = 'CONFIRMED';
                $b['amountPaid'] = $b['total'];
                $b['entitlementId'] = $eid;
                $b['rowVersion']++;
                $this->s['bookings'][$b['id']] = $b;
                $this->s['entitlements'][$eid] = $this->makeEntitlement($eid, $b['resourceName'].' - '.CarbonImmutable::parse($b['start'])->setTimezone(config('r007.display_timezone'))->format('D j M, H:i'), $b['facilityId'], $b['start'], $b['end'], $guest['name'], 'guest:'.$ref, ['bookingId' => $b['id']]);
            } elseif (isset($sub['orderIds'])) {
                $ord = $this->s['orders'][$sub['orderIds'][0]];
                $ord['status'] = 'PAID';
                $visit = CarbonImmutable::parse($ord['visitDate'], config('r007.display_timezone'));
                foreach ($ord['tickets'] as $i => $name) {
                    $eid = (string) Str::uuid();
                    $this->s['entitlements'][$eid] = $this->makeEntitlement($eid, $name, $ord['facilityId'], $visit->utc()->format('Y-m-d\TH:i:s\Z'), $visit->endOfDay()->utc()->format('Y-m-d\TH:i:s\Z'), $i === 0 ? $guest['name'] : null, 'guest:'.$ref, ['orderId' => $ord['id']]);
                }
                $this->s['orders'][$ord['id']] = $ord;
            } elseif (isset($sub['membershipId'])) {
                $this->s['memberships'][$sub['membershipId']]['status'] = 'ACTIVE';
            }
            $this->s['gorders'][$ref]['paid'] = true;
        }
        MockState::put($this->s);
    }

    /** @return array<string, mixed> */
    private function guestOrderView(array $o): array
    {
        $sub = $o['subject'];
        $view = ['reference' => $o['reference'], 'kind' => $o['kind'], 'paid' => ! empty($o['paid']), 'currency' => 'NGN', 'contact' => $o['guest'], 'claimed' => false, 'createdAt' => $o['createdAt'],
            'booking' => null, 'ticketOrder' => null, 'membership' => null, 'tickets' => []];
        if (isset($sub['bookingId'])) {
            $b = $this->publicBooking($this->expireHold($this->s['bookings'][$sub['bookingId']]));
            unset($b['customer']);
            $view['booking'] = $b;
            $view['status'] = $b['status'];
            $view['amountDue'] = $view['paid'] ? '0.0000' : $b['total'];
        } elseif (isset($sub['orderIds'])) {
            $ord = $this->s['orders'][$sub['orderIds'][0]];
            $view['ticketOrder'] = ['id' => $ord['id'], 'status' => $ord['status'], 'total' => $ord['total'], 'visitDate' => $ord['visitDate'], 'facilityId' => $ord['facilityId']];
            $view['status'] = $ord['status'];
            $view['amountDue'] = $view['paid'] ? '0.0000' : $ord['total'];
        } else {
            $m = $this->s['memberships'][$sub['membershipId']];
            $view['membership'] = $m + ['cards' => $m['status'] === 'ACTIVE' ? [['id' => $m['id'], 'qrToken' => $m['qrToken']]] : []];
            unset($view['membership']['customerId']);
            $view['status'] = $m['status'];
            $view['amountDue'] = $view['paid'] ? '0.0000' : $m['price'];
        }
        foreach ($this->s['entitlements'] as $e) {
            if (($e['customerId'] ?? null) === 'guest:'.$o['reference']) {
                unset($e['customerId']);
                $view['tickets'][] = $e;
            }
        }

        return $view;
    }

    private function guestResend(array $o): array
    {
        if (empty($o['paid'])) {
            $this->problem(409, 'nothing_to_send', 'Nothing to send yet');
        }

        return ['queued' => true];
    }

    private function guestCreateAccount(array $o, array $j): array
    {
        try {
            $this->register(['name' => $o['guest']['name'], 'email' => $o['guest']['email'], 'phone' => $o['guest']['phone'], 'password' => (string) ($j['password'] ?? '')]);
        } catch (R007ApiException) {
            // generic answer whether or not an account exists
        }

        return ['accepted' => true];
    }

    // ---- utils -----------------------------------------------------------

    /** @param array<mixed> $items @return array{items: array<mixed>, nextCursor: null} */
    private function page(array $items): array
    {
        return ['items' => array_values($items), 'nextCursor' => null];
    }

    private function minor(string $amount): int
    {
        [$w, $f] = array_pad(explode('.', $amount), 2, '0');

        return (int) $w * 100 + (int) substr(str_pad($f, 2, '0'), 0, 2);
    }

    private function fromMinor(int $k): string
    {
        return intdiv($k, 100).'.'.str_pad((string) ($k % 100), 2, '0', STR_PAD_LEFT).'00';
    }

    private function mul(string $amount, int $qty): string
    {
        return $this->fromMinor($this->minor($amount) * $qty);
    }

    private function add(string $a, string $b): string
    {
        return $this->fromMinor($this->minor($a) + $this->minor($b));
    }

    private function problem(int $status, string $code, string $title, ?string $detail = null, array $errors = []): never
    {
        MockState::put($this->s);
        throw new R007ApiException($status, $title, $detail, extensions: ['code' => $code] + ($errors ? ['errors' => $errors] : []));
    }
}

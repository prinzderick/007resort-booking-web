<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\ApiTestCase;

class SportsBookingTest extends ApiTestCase
{
    private function baseFakes(array $extra = []): void
    {
        $this->fakeApi($extra + [
            'public/site' => Http::response($this->siteBody()),
            'bookings/resources*' => $this->page([$this->court()]),
        ]);
    }

    private function slotsBody(): array
    {
        $day = now('Africa/Lagos')->addDay()->startOfDay();

        return ['resourceId' => self::COURT, 'slots' => [
            ['start' => $day->copy()->setTime(9, 0)->utc()->format('Y-m-d\TH:i:s\Z'), 'end' => $day->copy()->setTime(10, 0)->utc()->format('Y-m-d\TH:i:s\Z'), 'available' => true, 'remainingCapacity' => 1, 'price' => '5000.0000'],
            ['start' => $day->copy()->setTime(10, 0)->utc()->format('Y-m-d\TH:i:s\Z'), 'end' => $day->copy()->setTime(11, 0)->utc()->format('Y-m-d\TH:i:s\Z'), 'available' => false, 'remainingCapacity' => 0, 'price' => '5000.0000'],
        ]];
    }

    public function test_court_list_and_slot_grid_come_from_the_api(): void
    {
        $this->baseFakes(['bookings/resources/'.self::COURT.'/availability*' => Http::response($this->slotsBody())]);

        $this->get('/book/sports-arena')->assertOk()->assertSee('Tennis Court 1')->assertSee('₦5,000');

        $date = now('Africa/Lagos')->addDay()->format('Y-m-d');
        $this->get('/book/sports-arena/'.self::COURT."?date={$date}")->assertOk()
            ->assertSee('09:00')->assertSee('Taken')->assertSee('disabled', false);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/availability') && $r->data()['from'] === now('Africa/Lagos')->addDay()->startOfDay()->utc()->format('Y-m-d\TH:i:s\Z'));
    }

    public function test_slot_grid_shows_a_calm_notice_when_online_booking_is_disabled_for_the_resource(): void
    {
        $this->baseFakes(['bookings/resources/*/availability*' => $this->problemResponse(409, 'capability_disabled', 'Site heartbeat stale')]);

        $this->get('/book/sports-arena/'.self::COURT)->assertOk()
            ->assertSee('temporarily unavailable')->assertSee('Contact us')->assertDontSee('name="slot"', false);
    }

    public function test_slot_grid_degrades_when_the_api_is_down(): void
    {
        $this->baseFakes(['bookings/resources/*/availability*' => Http::response('', 503)]);

        $this->get('/book/sports-arena/'.self::COURT)->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_guest_can_hold_a_slot_without_signing_in_using_the_service_credential(): void
    {
        $this->baseFakes(['bookings/hold' => Http::response($this->booking(), 201)]);

        $this->post('/book/sports-arena/'.self::COURT.'/hold', ['slot' => '2026-09-24T08:00:00Z|2026-09-24T09:00:00Z', '_submission' => (string) Str::uuid()])
            ->assertRedirect(route('checkout.show', self::BOOKING));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/bookings/hold') && $r->hasHeader('Authorization', 'Bearer svc-test-token') && ! isset($r['customer']['email']));
    }

    public function test_hold_posts_slot_customer_and_idempotency_key_then_goes_to_checkout(): void
    {
        $this->baseFakes(['bookings/hold' => Http::response($this->booking(), 201)]);
        $sub = (string) Str::uuid();

        $this->signIn()->post('/book/sports-arena/'.self::COURT.'/hold', ['slot' => '2026-09-24T08:00:00Z|2026-09-24T09:00:00Z', '_submission' => $sub])
            ->assertRedirect(route('checkout.show', self::BOOKING));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/bookings/hold')
            && $r->hasHeader('Idempotency-Key', 'hold:'.self::COURT.':'.$sub)
            && $r->hasHeader('Authorization', 'Bearer cust-token')
            && $r['resourceId'] === self::COURT && $r['start'] === '2026-09-24T08:00:00Z' && $r['end'] === '2026-09-24T09:00:00Z'
            && $r['customer']['name'] === 'Chinedu Eze' && $r['quantity'] === 1);
    }

    public function test_double_click_only_holds_once(): void
    {
        $this->baseFakes(['bookings/hold' => Http::response($this->booking(), 201)]);
        $payload = ['slot' => '2026-09-24T08:00:00Z|2026-09-24T09:00:00Z', '_submission' => (string) Str::uuid()];

        $this->signIn()->post('/book/sports-arena/'.self::COURT.'/hold', $payload)->assertRedirect(route('checkout.show', self::BOOKING));
        $this->post('/book/sports-arena/'.self::COURT.'/hold', $payload)->assertRedirect(route('checkout.show', self::BOOKING));

        $this->assertCount(1, $this->sentTo('POST', 'bookings/hold'));
    }

    public function test_slot_unavailable_409_returns_to_a_fresh_grid_with_a_friendly_message(): void
    {
        $this->baseFakes(['bookings/hold' => $this->problemResponse(409, 'slot_unavailable', 'That slot was just taken')]);
        $start = '2026-09-24T08:00:00Z';

        $this->signIn()->post('/book/sports-arena/'.self::COURT.'/hold', ['slot' => "$start|2026-09-24T09:00:00Z", '_submission' => (string) Str::uuid()])
            ->assertRedirect(route('book.slots', ['sports-arena', self::COURT, 'date' => '2026-09-24']))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'just been taken'));
    }

    public function test_failed_hold_can_be_retried_with_a_new_form(): void
    {
        $this->baseFakes(['bookings/hold' => Http::sequence()->push($this->problem(409, 'slot_unavailable'), 409)->push($this->booking(), 201)]);

        $this->signIn()->post('/book/sports-arena/'.self::COURT.'/hold', ['slot' => '2026-09-24T08:00:00Z|2026-09-24T09:00:00Z', '_submission' => (string) Str::uuid()])->assertSessionHas('error');
        $this->post('/book/sports-arena/'.self::COURT.'/hold', ['slot' => '2026-09-24T09:00:00Z|2026-09-24T10:00:00Z', '_submission' => (string) Str::uuid()])->assertRedirect(route('checkout.show', self::BOOKING));
    }

    public function test_hold_rejects_malformed_slot(): void
    {
        $this->baseFakes();

        $this->signIn()->post('/book/sports-arena/'.self::COURT.'/hold', ['slot' => 'nonsense', '_submission' => (string) Str::uuid()])->assertSessionHasErrors('slot');
        $this->assertSame([], $this->sentTo('POST', 'bookings/hold'));
    }

    public function test_checkout_shows_countdown_from_server_time_and_total(): void
    {
        $this->baseFakes(['bookings/'.self::BOOKING => Http::response($this->booking())]);

        $res = $this->signIn()->get('/checkout/'.self::BOOKING)->assertOk()->assertSee('₦5,000')->assertSee('data-countdown', false)->assertSee('Pay ₦5,000');
        $this->assertMatchesRegularExpression('/data-countdown="(59\d|600)"/', $res->getContent());
    }

    public function test_expired_hold_shows_expired_state_and_no_pay_button(): void
    {
        $this->baseFakes(['bookings/'.self::BOOKING => Http::response($this->booking(['status' => 'HELD', 'holdExpiresAt' => now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z')]))]);

        $this->signIn()->get('/checkout/'.self::BOOKING)->assertOk()->assertSee('Your hold has ended')->assertDontSee('Pay ₦5,000');
    }

    public function test_pay_initialises_paystack_with_the_api_total_and_redirects_to_the_provider(): void
    {
        $this->baseFakes([
            'bookings/'.self::BOOKING => Http::response($this->booking()),
            'payments/paystack/initialize' => Http::response(['paymentId' => '0192f6a0-0000-7000-8000-000000000701', 'reference' => 'R007-REF-0001', 'authorizationUrl' => 'https://checkout.paystack.com/abc123'], 201),
        ]);
        $sub = (string) Str::uuid();

        $this->signIn()->post('/checkout/'.self::BOOKING.'/pay', ['_submission' => $sub, 'amount' => '1.00'])
            ->assertRedirect('https://checkout.paystack.com/abc123')
            ->assertSessionHas('checkout.pending.R007-REF-0001.id', self::BOOKING);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'payments/paystack/initialize')
            && $r['bookingId'] === self::BOOKING && $r['amount'] === '5000.0000' && $r['email'] === 'chinedu@example.com'
            && $r['callbackUrl'] === route('payment.return') && $r->hasHeader('Idempotency-Key', 'pay:'.self::BOOKING.':'.$sub));
    }

    public function test_pay_double_submit_initialises_once(): void
    {
        $this->baseFakes([
            'bookings/'.self::BOOKING => Http::response($this->booking()),
            'payments/paystack/initialize' => Http::response(['paymentId' => 'p', 'reference' => 'R007-REF-0002', 'authorizationUrl' => 'https://checkout.paystack.com/x'], 201),
        ]);
        $payload = ['_submission' => (string) Str::uuid()];

        $this->signIn()->post('/checkout/'.self::BOOKING.'/pay', $payload)->assertRedirect('https://checkout.paystack.com/x');
        $this->post('/checkout/'.self::BOOKING.'/pay', $payload)->assertRedirect('https://checkout.paystack.com/x');

        $this->assertCount(1, $this->sentTo('POST', 'payments/paystack/initialize'));
    }

    public function test_pay_after_hold_expiry_never_reaches_paystack(): void
    {
        $this->baseFakes(['bookings/'.self::BOOKING => Http::response($this->booking(['holdExpiresAt' => now()->subSecond()->utc()->format('Y-m-d\TH:i:s\Z')]))]);

        $this->signIn()->post('/checkout/'.self::BOOKING.'/pay', ['_submission' => (string) Str::uuid()])->assertRedirect(route('checkout.show', self::BOOKING));
        $this->assertSame([], $this->sentTo('POST', 'paystack/initialize'));
    }

    public function test_pay_hold_expired_from_api_is_explained(): void
    {
        $this->baseFakes([
            'bookings/'.self::BOOKING => Http::response($this->booking()),
            'payments/paystack/initialize' => $this->problemResponse(409, 'hold_expired'),
        ]);

        $this->signIn()->post('/checkout/'.self::BOOKING.'/pay', ['_submission' => (string) Str::uuid()])
            ->assertRedirect(route('checkout.show', self::BOOKING))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'expired'));
    }

    public function test_release_cancels_the_hold_through_the_api_with_if_match(): void
    {
        $this->baseFakes(['bookings/'.self::BOOKING => Http::response($this->booking()), 'bookings/'.self::BOOKING.'/cancel' => Http::response($this->booking(['status' => 'CANCELLED']))]);

        $this->signIn()->post('/checkout/'.self::BOOKING.'/release', ['_submission' => (string) Str::uuid()])->assertRedirect(route('account'));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/cancel') && $r->hasHeader('If-Match', '"3"'));
    }

    public function test_spa_and_salon_use_the_same_appointment_flow(): void
    {
        $this->fakeApi([
            'public/site' => Http::response($this->siteBody()),
            'bookings/resources*' => $this->page([$this->court(['id' => '0192f6a0-0000-7000-8000-000000000204', 'facilityId' => self::SPA, 'name' => 'Swedish Massage', 'price' => '25000.0000'])]),
        ]);

        $this->get('/book/beauty-spa')->assertOk()->assertSee('Swedish Massage')->assertSee('₦25,000')->assertSee('treatment');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'bookings/resources') && $r->data()['facilityId'] === self::SPA);
    }
}

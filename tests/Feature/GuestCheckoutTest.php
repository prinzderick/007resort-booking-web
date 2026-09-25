<?php

namespace Tests\Feature;

use App\Services\Cms\CmsClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ApiTestCase;

/**
 * Guest checkout: every purchase path works with name + email + phone, no login wall, no account.
 */
class GuestCheckoutTest extends ApiTestCase
{
    private const ADULT = '0192f6a0-0000-7000-8000-000000000301';

    private const CHILD = '0192f6a0-0000-7000-8000-000000000302';

    private const PLAN = '0192f6a0-0000-7000-8000-000000000401';

    private const REF = '007-ABC1234';

    private const TOKEN = 'tok_9f8e7d6c5b4a39281706f5e4d3c2b1a0';

    private const PAYREF = 'R007-PAY-0001';

    private function payment(): array
    {
        return ['payment' => ['reference' => self::PAYREF, 'authorizationUrl' => 'https://checkout.paystack.com/abc123']];
    }

    private function started(): array
    {
        return ['reference' => self::REF, 'accessToken' => self::TOKEN] + $this->payment();
    }

    /** @return array<string, mixed> */
    private function order(array $o = []): array
    {
        return $o + [
            'reference' => self::REF, 'kind' => 'booking', 'status' => 'PAID', 'total' => '5000.0000', 'title' => 'Tennis Court 1',
            'start' => now()->addDays(3)->setTime(9, 0)->utc()->format('Y-m-d\TH:i:s\Z'), 'end' => now()->addDays(3)->setTime(10, 0)->utc()->format('Y-m-d\TH:i:s\Z'),
            'facilityId' => self::SPORTS, 'resourceId' => self::COURT, 'quantity' => 1,
            'guest' => ['name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '+2348031234567'],
            'delivery' => ['available' => false, 'emailSent' => false],
            'tickets' => [['id' => self::ENT, 'qrToken' => 'signed.qr.token.abc123', 'name' => 'Tennis Court 1 - 09:00', 'holderName' => 'Ada Obi', 'validFrom' => null, 'validUntil' => null, 'status' => 'ACTIVE']],
            'policy' => ['canCancel' => true, 'cancelBy' => now()->addDays(2)->utc()->format('Y-m-d\TH:i:s\Z'), 'refundAmount' => '5000.0000', 'note' => 'Free cancellation up to 24 hours before start.'],
        ];
    }

    private function fake(array $extra = []): void
    {
        $this->fakeApi($extra + [
            'public/site' => Http::response($this->siteBody()),
            'bookings/resources*' => $this->page([$this->court()]),
            'catalog/products*' => $this->page([
                ['id' => self::ADULT, 'name' => 'Pool day ticket - Adult', 'kind' => 'TICKET', 'price' => '3000.0000', 'active' => true],
                ['id' => self::CHILD, 'name' => 'Pool day ticket - Child', 'kind' => 'TICKET', 'price' => '1500.0000', 'active' => true],
            ]),
            'memberships/plans*' => $this->page([['id' => self::PLAN, 'name' => 'Silver Monthly', 'durationDays' => 30, 'price' => '30000.0000', 'visitLimit' => 12, 'active' => true]]),
        ]);
    }

    /** @return array<string, string> */
    private function details(array $o = []): array
    {
        return $o + ['guest_name' => 'Ada Obi', 'guest_email' => 'Ada@Example.com', 'guest_phone' => '0803 123 4567', '_submission' => (string) Str::uuid()];
    }

    private function heldBooking(array $o = []): array
    {
        return $this->booking(['entitlementId' => null] + $o);
    }

    // ---- booking ---------------------------------------------------------

    public function test_guest_checkout_page_asks_only_for_name_email_phone_and_has_no_account_language(): void
    {
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking())]);

        $res = $this->get('/checkout/'.self::BOOKING)->assertOk()
            ->assertSee('Your details')->assertSee('Full name')->assertSee('We&rsquo;ll send your ticket here.', false)
            ->assertSee('name="guest_phone"', false)->assertSee('+234')->assertSee('Pay ₦5,000')->assertSee('data-countdown', false)
            ->assertSee('Already have an account?')->assertSee('Send me news and offers');
        $html = $res->getContent();
        $this->assertStringContainsString('autocomplete="name"', $html);
        $this->assertStringContainsString('autocomplete="email"', $html);
        $this->assertStringContainsString('inputmode="email"', $html);
        $this->assertStringContainsString('autocomplete="tel"', $html);
        $this->assertStringContainsString('inputmode="tel"', $html);
        $this->assertDoesNotMatchRegularExpression('/type="password"|create an account|sign up|register|create your account/i', substr($html, strpos($html, '<main')));
        $this->assertDoesNotMatchRegularExpression('/name="guest_marketing"[^>]*checked/', $html, 'news box is unticked by default');
    }

    public function test_order_pages_are_never_cached_never_indexed_and_send_no_referrer(): void
    {
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking())]);

        $this->get('/checkout/'.self::BOOKING)->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->assertStringContainsString('no-store', $this->get('/checkout/'.self::BOOKING)->headers->get('Cache-Control'));
        $this->get('/find-booking')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/booking/'.self::REF)->assertStatus(403)->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_pay_normalises_the_phone_and_starts_a_guest_order_with_the_service_credential(): void
    {
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking()), 'guest/checkout/booking' => Http::response($this->started(), 201)]);
        $d = $this->details();

        $this->post('/checkout/'.self::BOOKING.'/pay', $d)->assertRedirect('https://checkout.paystack.com/abc123');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/guest/checkout/booking')
            && $r->hasHeader('Authorization', 'Bearer svc-test-token')
            && $r->hasHeader('Idempotency-Key', 'pay:'.self::BOOKING.':'.$d['_submission'])
            && $r['bookingId'] === self::BOOKING
            && $r['guest'] === ['name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '+2348031234567', 'marketingConsent' => false, 'consentVersion' => config('r007.checkout.consent_version')]
            && $r['callbackUrl'] === route('payment.return'));
        $this->assertSame(self::TOKEN, session('guest.tokens')[self::REF]);
        $this->assertSame(['kind' => 'guest', 'order' => self::REF, 'attempts' => 0, 'flow' => 'booking'], session('checkout.pending.'.self::PAYREF));
    }

    public function test_a_customer_bearer_token_never_reaches_a_guest_order(): void
    {
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking()), 'guest/checkout/booking' => Http::response($this->started(), 201)]);
        // signed in but with an incomplete profile: they check out as a guest, on the service credential
        $this->withSession(['r007.api_token' => 'cust-token', 'r007.customer' => ['id' => 'c1', 'name' => 'Chinedu', 'email' => 'c@example.com', 'phone' => '']]);

        $this->post('/checkout/'.self::BOOKING.'/pay', $this->details())->assertRedirect('https://checkout.paystack.com/abc123');

        Http::assertNotSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer cust-token'));
    }

    /** @return array<string, array{string, string}> */
    public static function phones(): array
    {
        return ['zero' => ['08031234567', '+2348031234567'], 'no zero' => ['803 123 4567', '+2348031234567'], 'plus' => ['+234 803 123 4567', '+2348031234567'], '234' => ['2348031234567', '+2348031234567']];
    }

    #[DataProvider('phones')]
    public function test_phone_is_normalised_before_it_reaches_the_api(string $typed, string $e164): void
    {
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking()), 'guest/checkout/booking' => Http::response($this->started(), 201)]);

        $this->post('/checkout/'.self::BOOKING.'/pay', $this->details(['guest_phone' => $typed]))->assertRedirect();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/guest/checkout/booking') && $r['guest']['phone'] === $e164);
    }

    public function test_validation_errors_are_friendly_and_nothing_is_sent_to_the_api(): void
    {
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking())]);

        $this->from('/checkout/'.self::BOOKING)->post('/checkout/'.self::BOOKING.'/pay', $this->details(['guest_name' => '', 'guest_email' => 'ada@', 'guest_phone' => '12345']))
            ->assertRedirect('/checkout/'.self::BOOKING)
            ->assertSessionHasErrors(['guest_name', 'guest_email', 'guest_phone']);
        $this->assertSame([], $this->sentTo('POST', 'guest/checkout'));

        $page = $this->from('/checkout/'.self::BOOKING)->followingRedirects()->post('/checkout/'.self::BOOKING.'/pay', $this->details(['guest_email' => 'ada@', 'guest_phone' => 'abc']));
        $page->assertSee('Please fix these to continue')->assertSee('That email does not look right')->assertSee('Enter a Nigerian mobile number')->assertSee('data-error-summary', false)
            ->assertSee('aria-invalid="true"', false)->assertSee('value="ada@"', false);
    }

    public function test_news_and_offers_only_subscribes_when_the_box_is_ticked(): void
    {
        $cms = $this->mock(CmsClient::class);
        $cms->shouldReceive('subscribe')->once()->withArgs(fn ($email, $name, $source, $consent) => $email === 'ada@example.com' && $source === 'checkout' && $consent === true)->andReturn([]);
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking()), 'guest/checkout/booking' => Http::response($this->started(), 201)]);

        $this->post('/checkout/'.self::BOOKING.'/pay', $this->details(['guest_marketing' => '1']))->assertRedirect('https://checkout.paystack.com/abc123');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/guest/checkout/booking') && $r['guest']['marketingConsent'] === true);
    }

    public function test_unticked_news_box_never_touches_the_newsletter(): void
    {
        $cms = $this->mock(CmsClient::class);
        $cms->shouldNotReceive('subscribe');
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking()), 'guest/checkout/booking' => Http::response($this->started(), 201)]);

        $this->post('/checkout/'.self::BOOKING.'/pay', $this->details())->assertRedirect('https://checkout.paystack.com/abc123');
    }

    public function test_expired_hold_shows_a_friendly_recovery_and_pay_does_not_reach_the_api(): void
    {
        $expired = $this->heldBooking(['holdExpiresAt' => now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z')]);
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($expired)]);

        $this->get('/checkout/'.self::BOOKING)->assertOk()->assertSee('Your hold has ended')->assertSee('nothing has been charged', false)->assertSee('Pick a new time')->assertDontSee('name="guest_email"', false);
        $this->post('/checkout/'.self::BOOKING.'/pay', $this->details())->assertRedirect(route('checkout.show', self::BOOKING));
        $this->assertSame([], $this->sentTo('POST', 'guest/checkout'));
    }

    public function test_api_says_hold_expired_on_pay_is_shown_kindly_and_details_are_kept(): void
    {
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking()), 'guest/checkout/booking' => $this->problemResponse(409, 'hold_expired')]);

        $this->post('/checkout/'.self::BOOKING.'/pay', $this->details())->assertRedirect(route('checkout.show', self::BOOKING))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'expired'))->assertSessionHasInput('guest_email', 'Ada@Example.com');
    }

    public function test_double_click_only_starts_one_payment(): void
    {
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking()), 'guest/checkout/booking' => Http::response($this->started(), 201)]);
        $d = $this->details();

        $this->post('/checkout/'.self::BOOKING.'/pay', $d)->assertRedirect('https://checkout.paystack.com/abc123');
        $this->post('/checkout/'.self::BOOKING.'/pay', $d)->assertRedirect('https://checkout.paystack.com/abc123');
        $this->assertCount(1, $this->sentTo('POST', 'guest/checkout/booking'));
    }

    public function test_releasing_a_guest_hold_only_works_from_the_browser_that_made_it(): void
    {
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking()), 'guest/holds/'.self::BOOKING.'/release' => Http::response($this->heldBooking(['status' => 'CANCELLED']))]);

        $this->post('/checkout/'.self::BOOKING.'/release', ['_submission' => (string) Str::uuid()])->assertNotFound();
        $this->withSession(['guest.holds' => [self::BOOKING]])->post('/checkout/'.self::BOOKING.'/release', ['_submission' => (string) Str::uuid()])->assertRedirect()->assertSessionHas('status');
    }

    // ---- pool tickets & membership --------------------------------------

    public function test_pool_tickets_go_through_the_details_step_as_a_guest(): void
    {
        $this->fake(['guest/checkout/tickets' => Http::response($this->started(), 201)]);
        $date = now('Africa/Lagos')->addDay()->format('Y-m-d');

        $this->post('/pool/order', ['date' => $date, 'qty' => [self::ADULT => 2, self::CHILD => 1], '_submission' => (string) Str::uuid()])->assertRedirect(route('checkout.pool'));
        $this->assertSame([], $this->sentTo('POST', 'public/ticket-orders'));

        $this->get('/checkout/pool')->assertOk()->assertSee('Your details')->assertSee('2 x Pool day ticket - Adult')->assertSee('₦7,500')->assertSee('Pay ₦7,500')
            ->assertDontSee('type="password"', false);

        $this->post('/checkout/pool', $this->details())->assertRedirect('https://checkout.paystack.com/abc123');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/guest/checkout/tickets') && $r['facilityId'] === self::POOL && $r['visitDate'] === $date
            && $r['lines'] === [['productId' => self::ADULT, 'quantity' => 2], ['productId' => self::CHILD, 'quantity' => 1]] && $r['guest']['phone'] === '+2348031234567'
            && $r->hasHeader('Authorization', 'Bearer svc-test-token'));
        $this->assertSame(['kind' => 'guest', 'order' => self::REF, 'attempts' => 0, 'flow' => 'tickets'], session('checkout.pending.'.self::PAYREF));
    }

    public function test_pool_checkout_without_a_choice_sends_you_back_kindly(): void
    {
        $this->fake();
        $this->get('/checkout/pool')->assertRedirect(route('pool'));
        $this->post('/checkout/pool', $this->details())->assertRedirect(route('pool'));
    }

    public function test_pool_details_are_validated_too(): void
    {
        $this->fake();
        $date = now('Africa/Lagos')->addDay()->format('Y-m-d');
        $this->post('/pool/order', ['date' => $date, 'qty' => [self::ADULT => 1], '_submission' => (string) Str::uuid()]);

        $this->post('/checkout/pool', $this->details(['guest_phone' => '']))->assertSessionHasErrors('guest_phone');
        $this->assertSame([], $this->sentTo('POST', 'guest/checkout'));
    }

    public function test_membership_can_be_bought_as_a_guest(): void
    {
        $this->fake(['guest/checkout/membership' => Http::response($this->started(), 201)]);

        $this->get('/memberships')->assertOk()->assertSee('Choose this plan')->assertSee(route('checkout.membership', self::PLAN), false)->assertDontSee('Buy this plan');
        $this->get('/checkout/membership/'.self::PLAN)->assertOk()->assertSee('Silver Monthly')->assertSee('Pay ₦30,000')->assertSee('Your details');

        $this->post('/memberships/'.self::PLAN.'/buy', $this->details())->assertRedirect('https://checkout.paystack.com/abc123');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/guest/checkout/membership') && $r['planId'] === self::PLAN && $r['guest']['email'] === 'ada@example.com');
    }

    // ---- signed-in customers unchanged ----------------------------------

    public function test_signed_in_customer_skips_the_details_card_and_keeps_the_account_flow(): void
    {
        $this->fake(['bookings/'.self::BOOKING => Http::response($this->heldBooking()), 'payments/paystack/initialize' => Http::response(['paymentId' => 'p', 'reference' => self::PAYREF, 'authorizationUrl' => 'https://checkout.paystack.com/acct'])]);

        $this->signIn()->get('/checkout/'.self::BOOKING)->assertOk()->assertSee('Booking as')->assertSee('Chinedu Eze')->assertDontSee('name="guest_email"', false)->assertSee('Pay ₦5,000');
        $this->post('/checkout/'.self::BOOKING.'/pay', ['_submission' => (string) Str::uuid()])->assertRedirect('https://checkout.paystack.com/acct');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments/paystack/initialize') && $r->hasHeader('Authorization', 'Bearer cust-token'));
        $this->assertSame([], $this->sentTo('POST', 'guest/'));
    }

    // ---- payment return --------------------------------------------------

    private function pendingGuest(): static
    {
        return $this->withSession(['checkout.pending.'.self::PAYREF => ['kind' => 'guest', 'order' => self::REF, 'attempts' => 0, 'flow' => 'booking'], 'guest.tokens' => [self::REF => self::TOKEN]]);
    }

    public function test_return_success_goes_to_the_order_page_without_any_login(): void
    {
        $this->fake(['payments/paystack/verify/*' => Http::response(['status' => 'CAPTURED', 'reference' => self::PAYREF])]);

        $this->pendingGuest()->get('/payment/return?reference='.self::PAYREF)->assertRedirect(route('orders.show', self::REF))->assertSessionHas('just_paid', true)
            ->assertSessionMissing('checkout.pending.'.self::PAYREF);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'payments/paystack/verify/') && $r->hasHeader('Authorization', 'Bearer svc-test-token'));
    }

    public function test_return_failure_keeps_the_order_and_offers_a_retry(): void
    {
        $this->fake(['payments/paystack/verify/*' => Http::response(['status' => 'FAILED', 'reference' => self::PAYREF])]);

        $this->pendingGuest()->get('/payment/return?reference='.self::PAYREF)->assertOk()->assertSee('Payment was not completed')->assertSee('Try paying again')
            ->assertSee(route('orders.show', self::REF), false)->assertSee('order is saved');
        $this->assertSame(self::TOKEN, session('guest.tokens')[self::REF], 'the order is not lost');
    }

    public function test_return_still_processing_polls_and_unknown_or_paid_elsewhere_is_explained(): void
    {
        $this->fake(['payments/paystack/verify/*' => Http::sequence()->push(['status' => 'AUTHORIZING'])->push(['status' => 'CAPTURED'])]);

        $this->pendingGuest()->get('/payment/return?reference='.self::PAYREF)->assertOk()->assertSee('Confirming your payment')->assertSee('http-equiv="refresh"', false);
        $this->flushSession();
        $this->get('/payment/return?reference='.self::PAYREF)->assertOk()->assertSee('Find my booking');
    }

    public function test_return_when_the_api_is_down_says_so_truthfully(): void
    {
        $this->fake(['payments/paystack/verify/*' => Http::response('', 503)]);
        $this->pendingGuest()->get('/payment/return?reference='.self::PAYREF)->assertOk()->assertSee('could not check your payment yet');
    }

    // ---- token exchange and the order page ------------------------------

    public function test_manage_link_exchanges_the_token_into_the_session_and_redirects_to_a_clean_url(): void
    {
        $this->fake();

        $res = $this->get('/booking/'.self::REF.'?t='.self::TOKEN);
        $res->assertRedirect(route('orders.show', self::REF));
        $this->assertStringNotContainsString(self::TOKEN, $res->headers->get('Location'));
        $res->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame(self::TOKEN, session('guest.tokens')[self::REF]);
        $this->assertSame([], Http::recorded()->all(), 'exchange itself makes no API call');
    }

    public function test_malformed_tokens_are_not_stored(): void
    {
        $this->fake();
        $this->get('/booking/'.self::REF.'?t=<script>')->assertRedirect(route('find.show', ['reference' => self::REF]));
        $this->assertNull(session('guest.tokens'));
        $this->get('/booking/bad%20ref!')->assertNotFound();
    }

    public function test_paid_order_page_shows_qr_details_actions_and_a_truthful_delivery_line(): void
    {
        $this->fake(['guest/orders/'.self::REF => Http::response($this->order(['delivery' => ['available' => true, 'emailSent' => true]]))]);

        $res = $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]])->get('/booking/'.self::REF)->assertOk()
            ->assertSee("You're booked.")->assertSee('We&rsquo;ve sent your ticket to', false)->assertSee('a**@example.com')
            ->assertSee('data:image/svg+xml;base64', false)->assertSee('Save ticket')->assertSee('Add to calendar')->assertSee(route('orders.ics', self::REF), false)
            ->assertSee('Your private link')->assertSee('/booking/'.self::REF.'?t='.self::TOKEN, false)->assertSee(self::REF)
            ->assertSee('Free cancellation')->assertSee('Cancel this booking')->assertSee('Send it again');
        $this->assertStringNotContainsString('ada@example.com', $res->getContent(), 'email is masked on screen');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/guest/orders/'.self::REF) && $r->hasHeader('X-Order-Token', self::TOKEN) && $r->hasHeader('Authorization', 'Bearer svc-test-token'));
    }

    public function test_when_email_delivery_is_unavailable_the_page_does_not_claim_an_email_was_sent(): void
    {
        $this->fake(['guest/orders/'.self::REF => Http::response($this->order())]);

        $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]])->get('/booking/'.self::REF)->assertOk()
            ->assertSee('Keep this page or use the link below')->assertDontSee('We&rsquo;ve sent your ticket', false)->assertDontSee('Send it again');
    }

    public function test_multi_ticket_orders_render_one_qr_each(): void
    {
        $t = fn ($n) => ['id' => Str::uuid()->toString(), 'qrToken' => 'qr.'.$n, 'name' => 'Pool day ticket', 'holderName' => null, 'validFrom' => null, 'validUntil' => null, 'status' => 'ACTIVE'];
        $this->fake(['guest/orders/'.self::REF => Http::response($this->order(['kind' => 'tickets', 'title' => 'Pool day passes', 'start' => null, 'end' => null, 'visitDate' => now()->addDay()->format('Y-m-d'), 'tickets' => [$t(1), $t(2), $t(3)], 'policy' => []]))]);

        $res = $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]])->get('/booking/'.self::REF)->assertOk()->assertSee('Your tickets are ready.');
        $this->assertSame(3, substr_count($res->getContent(), 'data-qr'));
        $res->assertSee('ticket 2 of 3');
    }

    public function test_order_without_access_or_with_a_revoked_token_is_a_generic_locked_page(): void
    {
        $this->fake(['guest/orders/*' => $this->problemResponse(403, 'permission_denied')]);

        $this->get('/booking/'.self::REF)->assertStatus(403)->assertSee('Open your booking')->assertSee('Find my booking');
        $this->withSession(['guest.tokens' => [self::REF => 'stale_token_value_0123456789']])->get('/booking/'.self::REF)->assertStatus(403)->assertSee('Open your booking');
        $this->assertArrayNotHasKey(self::REF, (array) session('guest.tokens'), 'a rejected token is forgotten');
    }

    public function test_pending_order_offers_a_retry_with_the_same_hold(): void
    {
        $pending = $this->order(['status' => 'PENDING_PAYMENT', 'tickets' => [], 'holdExpiresAt' => now()->addMinutes(6)->utc()->format('Y-m-d\TH:i:s\Z')]);
        $this->fake(['guest/orders/'.self::REF => Http::response($pending), 'guest/orders/'.self::REF.'/pay' => Http::response($this->payment() + ['reference' => self::REF])]);

        $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]])->get('/booking/'.self::REF)->assertOk()->assertSee('Payment not finished')->assertSee('Try paying again')->assertSee('data-countdown', false);

        $this->post('/booking/'.self::REF.'/pay', ['_submission' => (string) Str::uuid()])->assertRedirect('https://checkout.paystack.com/abc123');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/guest/orders/'.self::REF.'/pay') && $r->hasHeader('X-Order-Token', self::TOKEN) && $r['callbackUrl'] === route('payment.return'));
        $this->assertSame(self::REF, session('checkout.pending.'.self::PAYREF)['order']);
    }

    public function test_pending_order_whose_hold_ended_says_so(): void
    {
        $pending = $this->order(['status' => 'PENDING_PAYMENT', 'tickets' => [], 'holdExpiresAt' => now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z')]);
        $this->fake(['guest/orders/'.self::REF => Http::response($pending)]);

        $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]])->get('/booking/'.self::REF)->assertOk()->assertSee('The slot hold has ended')->assertDontSee('Try paying again');
    }

    public function test_paid_but_tickets_not_issued_yet_refreshes_itself(): void
    {
        $this->fake(['guest/orders/'.self::REF => Http::response($this->order(['tickets' => []]))]);
        $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]])->get('/booking/'.self::REF)->assertOk()->assertSee('Confirming your payment')->assertSee('http-equiv="refresh"', false);
    }

    public function test_resend_message_is_truthful(): void
    {
        $this->fake(['guest/orders/'.self::REF.'/resend' => Http::sequence()->push(['available' => true, 'emailSent' => true])->push(['available' => false, 'emailSent' => false])]);
        $s = $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]]);

        $s->post('/booking/'.self::REF.'/resend', ['_submission' => (string) Str::uuid()])->assertSessionHas('status', fn ($m) => str_contains($m, 'sent your ticket again'));
        $this->post('/booking/'.self::REF.'/resend', ['_submission' => (string) Str::uuid()])->assertSessionHas('notice', fn ($m) => str_contains($m, 'cannot send emails right now'));
    }

    public function test_guest_can_cancel_where_the_api_allows_and_sees_the_api_reason_otherwise(): void
    {
        $this->fake(['guest/orders/'.self::REF.'/cancel' => Http::sequence()->push($this->order(['status' => 'CANCELLED']))->push($this->problem(409, 'order_state_invalid', 'Cannot cancel', 'The cancellation window closed 24 hours before the start time.'), 409)]);
        $s = $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]]);

        $s->post('/booking/'.self::REF.'/cancel', ['reason' => 'Rain', '_submission' => (string) Str::uuid()])->assertRedirect(route('orders.show', self::REF))->assertSessionHas('status');
        $this->post('/booking/'.self::REF.'/cancel', ['_submission' => (string) Str::uuid()])->assertSessionHas('error', fn ($m) => str_contains($m, 'window closed'));
    }

    public function test_calendar_file_for_a_booking(): void
    {
        $this->fake(['guest/orders/'.self::REF => Http::response($this->order())]);

        $res = $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]])->get('/booking/'.self::REF.'/calendar.ics')->assertOk();
        $this->assertStringContainsString('text/calendar', $res->headers->get('Content-Type'));
        $body = $res->getContent();
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('DTSTART:', $body);
        $this->assertStringContainsString('Tennis Court 1 at 007 Resort & Spa', $body);
        $this->assertStringNotContainsString(self::TOKEN, $body);
    }

    // ---- soft account prompt --------------------------------------------

    public function test_account_prompt_only_appears_right_after_paying_and_asks_only_for_a_password(): void
    {
        $this->fake(['guest/orders/'.self::REF => Http::response($this->order())]);
        $s = $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]]);

        $s->get('/booking/'.self::REF)->assertOk()->assertDontSee('all your bookings in one place');
        $this->withSession(['just_paid' => true])->get('/booking/'.self::REF)->assertOk()->assertSee('Want all your bookings in one place?')->assertSee('Choose a password')->assertSee('data-acct-dismiss', false);
    }

    public function test_account_prompt_creates_the_account_from_the_same_details(): void
    {
        $this->fake(['guest/orders/'.self::REF => Http::response($this->order()), 'customer/auth/register' => Http::response(['customerId' => 'c1', 'verificationRequired' => true])]);

        $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]])->post('/booking/'.self::REF.'/account', ['password' => 'a-long-password-1', '_submission' => (string) Str::uuid()])
            ->assertRedirect(route('verify', ['email' => 'ada@example.com']));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/customer/auth/register') && $r['name'] === 'Ada Obi' && $r['email'] === 'ada@example.com' && $r['phone'] === '+2348031234567');
    }

    // ---- find my booking -------------------------------------------------

    public function test_find_booking_by_reference_and_email_opens_the_order(): void
    {
        $this->fake(['guest/orders/lookup' => Http::response(['reference' => self::REF, 'accessToken' => self::TOKEN])]);

        $this->get('/find-booking')->assertOk()->assertSee('Find my')->assertSee('Booking reference')->assertSee('Email or phone number')->assertDontSee('cf-turnstile');
        $this->post('/find-booking', ['reference' => ' 007-abc1234 ', 'contact' => 'Ada@Example.com'])->assertRedirect(route('orders.show', self::REF));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/guest/orders/lookup') && $r['reference'] === '007-ABC1234' && $r['contact'] === 'ada@example.com' && $r->hasHeader('Authorization', 'Bearer svc-test-token'));
        $this->assertSame(self::TOKEN, session('guest.tokens')[self::REF]);
    }

    public function test_find_booking_by_phone_normalises_the_number(): void
    {
        $this->fake(['guest/orders/lookup' => Http::response(['reference' => self::REF, 'accessToken' => self::TOKEN])]);
        $this->post('/find-booking', ['reference' => self::REF, 'contact' => '0803 123 4567'])->assertRedirect(route('orders.show', self::REF));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/guest/orders/lookup') && $r['contact'] === '+2348031234567');
    }

    public function test_lookup_mismatch_shows_one_generic_message_whatever_was_wrong(): void
    {
        $this->fake(['guest/orders/lookup' => $this->problemResponse(404, 'not_found')]);

        $a = $this->from('/find-booking')->post('/find-booking', ['reference' => self::REF, 'contact' => 'nobody@example.com'])->assertSessionHasErrors('form');
        $msg = session('errors')->first('form');
        $this->assertStringContainsString('could not find a booking with those details', $msg);
        $this->assertNull(session('guest.tokens'));

        // malformed input gets the very same wording (no oracle) and never reaches the API
        $this->flushSession();
        $this->from('/find-booking')->post('/find-booking', ['reference' => '!!', 'contact' => 'x@'])->assertSessionHasErrors('form');
        $this->assertSame($msg, session('errors')->first('form'));
        $this->assertCount(1, $this->sentTo('POST', 'guest/orders/lookup'));
    }

    public function test_lookup_is_rate_limited_with_a_friendly_message(): void
    {
        $this->fake(['guest/orders/lookup' => $this->problemResponse(429, 'rate_limited')]);
        $this->from('/find-booking')->post('/find-booking', ['reference' => self::REF, 'contact' => 'a@example.com'])->assertSessionHasErrors('form');
        $this->assertStringContainsString('Too many tries', session('errors')->first('form'));

        $this->flushSession();
        $this->fake(['guest/orders/lookup' => $this->problemResponse(404, 'not_found')]);
        for ($i = 0; $i < 2; $i++) {
            $this->post('/find-booking', ['reference' => self::REF, 'contact' => 'a@example.com'])->assertSessionHasErrors('form');
        }
        $this->post('/find-booking', ['reference' => self::REF, 'contact' => 'a@example.com'])->assertStatus(429);
    }

    public function test_turnstile_is_off_by_default_and_rendered_and_enforced_when_configured(): void
    {
        $this->fake(['guest/orders/lookup' => Http::response(['reference' => self::REF, 'accessToken' => self::TOKEN]), 'challenges.cloudflare.com/*' => Http::response(['success' => false])]);
        config(['r007.checkout.turnstile_site_key' => '1x00000000000000000000AA', 'r007.checkout.turnstile_secret' => 'secret']);

        $this->get('/find-booking')->assertOk()->assertSee('cf-turnstile', false)->assertSee('1x00000000000000000000AA', false);
        $this->post('/find-booking', ['reference' => self::REF, 'contact' => 'a@example.com'])->assertSessionHasErrors('form');
        $this->assertSame([], $this->sentTo('POST', 'guest/orders/lookup'));
    }

    public function test_find_booking_is_linked_from_the_footer_and_the_header_menu(): void
    {
        $this->fake();
        $this->get('/')->assertOk()->assertSee(route('find.show'), false)->assertSee('Find my booking');
    }

    // ---- privacy ---------------------------------------------------------

    public function test_access_tokens_and_personal_details_are_not_logged(): void
    {
        Log::spy();
        $this->fake(['guest/holds/'.self::BOOKING => Http::response($this->heldBooking()), 'guest/checkout/booking' => Http::response($this->started(), 201), 'guest/orders/'.self::REF => $this->problemResponse(500, 'boom')]);

        $this->post('/checkout/'.self::BOOKING.'/pay', $this->details());
        $this->withSession(['guest.tokens' => [self::REF => self::TOKEN]])->get('/booking/'.self::REF);

        Log::shouldNotHaveReceived('warning', fn ($msg, $ctx = []) => str_contains(json_encode([$msg, $ctx]), self::TOKEN) || str_contains(json_encode([$msg, $ctx]), 'ada@example.com'));
        $this->assertTrue(true);
    }
}

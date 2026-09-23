<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\ApiTestCase;

/**
 * The Paystack return URL is untrusted input. Outcomes come only from the API's
 * verify endpoint; webhooks never reach this app.
 */
class PaymentReturnTest extends ApiTestCase
{
    private const REF = 'R007-REF-0001';

    private function payment(string $status): array
    {
        return ['id' => 'p1', 'reference' => self::REF, 'status' => $status, 'amount' => '5000.0000', 'currency' => 'NGN'];
    }

    private function bookingPending(): static
    {
        return $this->signIn()->pending(self::REF, ['kind' => 'booking', 'id' => self::BOOKING]);
    }

    public function test_captured_payment_with_confirmed_booking_redirects_to_the_qr_ticket(): void
    {
        $this->fakeApi([
            'payments/paystack/verify/'.self::REF => Http::response($this->payment('CAPTURED')),
            'bookings/'.self::BOOKING => Http::response($this->booking(['status' => 'CONFIRMED', 'entitlementId' => self::ENT, 'amountPaid' => '5000.0000'])),
        ]);

        $this->bookingPending()->get('/payment/return?reference='.self::REF)
            ->assertRedirect(route('tickets.show', self::ENT))
            ->assertSessionMissing('checkout.pending.'.self::REF);

        $this->assertSame([], $this->sentTo('POST', '/confirm'), 'no confirm call needed when the API already confirmed');
    }

    public function test_captured_payment_but_booking_not_yet_confirmed_asks_the_api_to_confirm(): void
    {
        $this->fakeApi([
            'payments/paystack/verify/'.self::REF => Http::response($this->payment('CAPTURED')),
            'bookings/'.self::BOOKING.'/confirm' => Http::response($this->booking(['status' => 'CONFIRMED', 'entitlementId' => self::ENT, 'rowVersion' => 4])),
            'bookings/'.self::BOOKING => Http::response($this->booking()),
        ]);

        $this->bookingPending()->get('/payment/return?reference='.self::REF)->assertRedirect(route('tickets.show', self::ENT));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/confirm') && $r['paystackReference'] === self::REF && $r->hasHeader('If-Match', '"3"'));
    }

    public function test_confirm_race_409_falls_back_to_reading_the_booking(): void
    {
        $this->fakeApi([
            'payments/paystack/verify/'.self::REF => Http::response($this->payment('CAPTURED')),
            'bookings/'.self::BOOKING.'/confirm' => $this->problemResponse(409, 'concurrency_conflict'),
            'bookings/'.self::BOOKING => Http::sequence()->push($this->booking())->push($this->booking(['status' => 'CONFIRMED', 'entitlementId' => self::ENT])),
        ]);

        $this->bookingPending()->get('/payment/return?reference='.self::REF)->assertRedirect(route('tickets.show', self::ENT));
    }

    public function test_query_string_claims_are_ignored_failed_payment_stays_failed(): void
    {
        $this->fakeApi(['payments/paystack/verify/'.self::REF => Http::response($this->payment('FAILED'))]);

        $this->bookingPending()->get('/payment/return?reference='.self::REF.'&status=success&trxref='.self::REF)
            ->assertOk()->assertSee('Payment was not completed')->assertSee('Try paying again');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'payments/paystack/verify/'.self::REF));
        $this->assertSame([], $this->sentTo('POST', '/confirm'));
    }

    public function test_verification_always_goes_through_the_api(): void
    {
        $this->fakeApi(['payments/paystack/verify/'.self::REF => Http::response($this->payment('AUTHORIZING'))]);

        $this->bookingPending()->get('/payment/return?reference='.self::REF)->assertOk()->assertSee('Confirming your payment');
        $this->assertCount(1, $this->sentTo('GET', 'payments/paystack/verify/'.self::REF));
    }

    public function test_still_authorizing_shows_processing_with_auto_refresh_then_gives_up_calmly(): void
    {
        $this->fakeApi(['payments/paystack/verify/'.self::REF => Http::response($this->payment('AUTHORIZING'))]);

        $this->bookingPending()->get('/payment/return?reference='.self::REF)->assertSee('http-equiv="refresh"', false);

        $this->withSession(['checkout.pending.'.self::REF => ['kind' => 'booking', 'id' => self::BOOKING, 'attempts' => 12]]);
        $this->get('/payment/return?reference='.self::REF)->assertSee('Still confirming')->assertDontSee('http-equiv="refresh"', false);
    }

    public function test_paid_but_hold_lost_is_never_hidden(): void
    {
        $this->fakeApi([
            'payments/paystack/verify/'.self::REF => Http::response($this->payment('CAPTURED')),
            'bookings/'.self::BOOKING => Http::response($this->booking(['status' => 'EXPIRED'])),
        ]);

        $this->bookingPending()->get('/payment/return?reference='.self::REF)->assertOk()->assertSee('received your payment but the slot was lost');
    }

    public function test_unknown_reference_or_junk_is_handled_safely(): void
    {
        $this->fakeApi(['payments/paystack/verify/*' => $this->problemResponse(404, 'not_found')]);

        $this->signIn()->get('/payment/return?reference=NOPE-NOPE-1')->assertOk()->assertSee('could not find that payment');
        $this->get('/payment/return?reference=../../etc/passwd')->assertOk()->assertSee('could not find that payment');
        $this->get('/payment/return')->assertOk()->assertSee('could not find that payment');
    }

    public function test_api_unreachable_during_verification_does_not_claim_success_or_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->bookingPending()->get('/payment/return?reference='.self::REF)->assertOk()->assertSee('could not check your payment yet')->assertDontSee('Payment received');
    }

    public function test_verified_payment_without_local_context_is_acknowledged_without_finalising(): void
    {
        $this->fakeApi(['payments/paystack/verify/'.self::REF => Http::response($this->payment('CAPTURED'))]);

        $this->signIn()->get('/payment/return?reference='.self::REF)->assertOk()->assertSee('Payment received');
        $this->assertSame([], $this->sentTo('POST', '/confirm'));
    }

    public function test_return_requires_sign_in(): void
    {
        $this->fakeApi([]);
        $this->get('/payment/return?reference='.self::REF)->assertRedirect(route('login'));
        $this->assertSame([], Http::recorded()->all());
    }

    public function test_the_app_exposes_no_payment_webhook_endpoint(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertStringNotContainsStringIgnoringCase('webhook', $route->uri());
        }
        $this->post('/payments/webhooks/paystack', [])->assertNotFound();
    }
}

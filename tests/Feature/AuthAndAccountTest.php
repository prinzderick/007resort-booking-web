<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\ApiTestCase;

class AuthAndAccountTest extends ApiTestCase
{
    private function registerPayload(array $o = []): array
    {
        return $o + [
            'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '+2348011112222', 'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery',
            '_ts' => Crypt::encryptString((string) (time() - 10)), 'website' => '', '_submission' => (string) Str::uuid(),
        ];
    }

    public function test_register_calls_the_api_customer_endpoint_and_goes_to_verification(): void
    {
        $this->fakeApi(['customer/auth/register' => Http::response(['customerId' => 'c1'], 201)]);

        $this->post('/register', $this->registerPayload())->assertRedirect(route('verify', ['email' => 'ada@example.com']));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'customer/auth/register') && $r['email'] === 'ada@example.com' && $r->hasHeader('Idempotency-Key') && ! isset($r['website']));
    }

    public function test_register_surfaces_api_field_errors(): void
    {
        $this->fakeApi(['customer/auth/register' => Http::response($this->problem(422, 'validation_failed') + ['errors' => ['email' => ['This email is already registered.']]], 422)]);

        $this->post('/register', $this->registerPayload())->assertSessionHasErrors(['email' => 'This email is already registered.']);
    }

    public function test_register_rejects_bots_honeypot_and_instant_submits(): void
    {
        $this->fakeApi([]);

        $this->post('/register', $this->registerPayload(['website' => 'http://spam.example']))->assertSessionHasErrors('form');
        $this->post('/register', $this->registerPayload(['_ts' => Crypt::encryptString((string) time())]))->assertSessionHasErrors('form');
        $this->post('/register', $this->registerPayload(['_ts' => 'garbage']))->assertSessionHasErrors('form');
        $this->assertSame([], Http::recorded()->all());
    }

    public function test_register_validates_password_strength_locally_before_calling_the_api(): void
    {
        $this->fakeApi([]);
        $this->post('/register', $this->registerPayload(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->assertSame([], Http::recorded()->all());
    }

    public function test_verify_with_token_signs_the_customer_in(): void
    {
        $this->fakeApi(['customer/auth/verify' => Http::response(['accessToken' => 'tok-1', 'customer' => ['id' => 'c1', 'name' => 'Ada Obi', 'email' => 'ada@example.com']])]);

        $this->post('/verify', ['email' => 'ada@example.com', 'code' => '123456'])->assertRedirect(route('account'));
        $this->assertSame('tok-1', session('r007.api_token'));
    }

    public function test_login_stores_token_server_side_and_uses_it_as_bearer(): void
    {
        $this->fakeApi([
            'customer/auth/login' => Http::response(['accessToken' => 'tok-abc', 'customer' => ['id' => 'c1', 'name' => 'Ada Obi', 'email' => 'ada@example.com']]),
            'customer/bookings*' => $this->page([]),
            'customer/memberships*' => $this->page([]),
        ]);

        $this->post('/login', ['email' => 'ada@example.com', 'password' => 'pw'])->assertRedirect(route('account'));
        $this->get('/account')->assertOk()->assertSee('Hello, Ada');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'customer/bookings') && $r->hasHeader('Authorization', 'Bearer tok-abc'));
    }

    public function test_login_never_exposes_the_token_to_the_browser(): void
    {
        $this->fakeApi(['customer/auth/login' => Http::response(['accessToken' => 'tok-secret-xyz', 'customer' => ['id' => 'c1', 'name' => 'Ada', 'email' => 'a@example.com']]), 'customer/*' => $this->page([])]);

        $res = $this->post('/login', ['email' => 'a@example.com', 'password' => 'pw']);
        $this->assertStringNotContainsString('tok-secret-xyz', $res->headers->__toString());
        $this->assertStringNotContainsString('tok-secret-xyz', $this->get('/account')->getContent());
    }

    public function test_invalid_credentials_show_a_friendly_error(): void
    {
        $this->fakeApi(['customer/auth/login' => $this->problemResponse(401, 'invalid_credentials')]);

        $this->post('/login', ['email' => 'ada@example.com', 'password' => 'bad'])->assertSessionHasErrors(['email' => 'Those details do not match an account.']);
        $this->assertNull(session('r007.api_token'));
    }

    public function test_unverified_login_is_sent_to_verification(): void
    {
        $this->fakeApi(['customer/auth/login' => Http::response($this->problem(403, 'permission_denied', 'Email not verified', 'Please verify your email address first.'), 403)]);

        $this->post('/login', ['email' => 'ada@example.com', 'password' => 'pw'])->assertRedirect(route('verify', ['email' => 'ada@example.com']));
    }

    public function test_login_is_rate_limited(): void
    {
        $this->fakeApi(['customer/auth/login' => $this->problemResponse(401, 'invalid_credentials')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'ada@example.com', 'password' => 'bad'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'ada@example.com', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_account_pages_require_sign_in_and_remember_the_destination(): void
    {
        $this->fakeApi([]);

        $this->get('/account/bookings')->assertRedirect(route('login'));
        $this->assertSame(url('/account/bookings'), session('url.intended'));
        $this->get('/tickets/'.self::ENT)->assertRedirect(route('login'));
        $this->get('/checkout/'.self::BOOKING)->assertRedirect(route('login'));
        $this->assertSame([], Http::recorded()->all());
    }

    public function test_expired_api_session_sends_the_customer_back_to_login(): void
    {
        $this->fakeApi(['customer/bookings*' => $this->problemResponse(401, 'token_expired')]);

        $this->signIn()->get('/account/bookings')->assertRedirect(route('login'));
        $this->assertNull(session('r007.api_token'));
    }

    public function test_logout_clears_the_session_and_calls_the_api(): void
    {
        $this->fakeApi(['customer/auth/logout' => Http::response([])]);

        $this->signIn()->post('/logout')->assertRedirect(route('home'));
        $this->assertNull(session('r007.api_token'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'customer/auth/logout'));
    }

    public function test_forms_reject_posts_without_a_csrf_token_outside_the_test_env(): void
    {
        $this->fakeApi([]);
        $this->app['env'] = 'production';

        $this->post('/login', ['email' => 'a@example.com', 'password' => 'x'])->assertStatus(419);
    }

    // ---- booking history / cancel / reschedule ----

    public function test_booking_history_lists_bookings_from_the_api_newest_first(): void
    {
        $this->fakeApi([
            'customer/bookings*' => $this->page([$this->booking(['status' => 'CONFIRMED', 'start' => '2026-10-01T09:00:00Z', 'end' => '2026-10-01T10:00:00Z']), $this->booking(['id' => 'b2', 'number' => 'BK-2', 'start' => '2026-11-01T09:00:00Z', 'end' => '2026-11-01T10:00:00Z', 'status' => 'CANCELLED'])]),
        ]);

        $this->signIn()->get('/account/bookings')->assertOk()->assertSeeInOrder(['BK-2', 'BK-20260923-0007'])->assertSee('Cancelled')->assertSee('Confirmed');
    }

    public function test_booking_history_degrades_when_api_is_unavailable(): void
    {
        $this->fakeApi(['customer/bookings*' => Http::response('', 503)]);

        $this->signIn()->get('/account/bookings')->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_booking_detail_follows_the_rules_the_api_returns(): void
    {
        $policy = ['canCancel' => false, 'canReschedule' => true, 'cancelBy' => '2026-09-25T09:00:00Z', 'refundAmount' => '0.0000', 'note' => 'Changes close 24h before start.'];
        $this->fakeApi([
            'bookings/'.self::BOOKING => Http::response($this->booking(['status' => 'CONFIRMED', 'policy' => $policy, 'entitlementId' => self::ENT])),
            'entitlements/'.self::ENT => Http::response($this->entitlement()),
        ]);

        $this->signIn()->get('/account/bookings/'.self::BOOKING)->assertOk()
            ->assertSee('Changes close 24h before start.')->assertSee('Reschedule')->assertDontSee('Cancel booking')->assertSee('Open ticket');
    }

    public function test_cancel_sends_reason_and_if_match_and_reports_success(): void
    {
        $this->fakeApi([
            'bookings/'.self::BOOKING => Http::response($this->booking(['status' => 'CONFIRMED'])),
            'bookings/'.self::BOOKING.'/cancel' => Http::response($this->booking(['status' => 'CANCELLED'])),
        ]);

        $this->signIn()->post('/account/bookings/'.self::BOOKING.'/cancel', ['reason' => 'Rain', '_submission' => (string) Str::uuid()])
            ->assertRedirect(route('account.bookings.show', self::BOOKING))->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/cancel') && $r['reason'] === 'Rain' && $r->hasHeader('If-Match', '"3"') && $r->hasHeader('Idempotency-Key'));
    }

    public function test_cancel_refused_by_api_policy_shows_the_api_explanation(): void
    {
        $this->fakeApi([
            'bookings/'.self::BOOKING => Http::response($this->booking(['status' => 'CONFIRMED'])),
            'bookings/'.self::BOOKING.'/cancel' => Http::response($this->problem(409, 'order_state_invalid', 'Cannot cancel', 'The cancellation window closed 24 hours before the start time.'), 409),
        ]);

        $this->signIn()->post('/account/bookings/'.self::BOOKING.'/cancel', ['_submission' => (string) Str::uuid()])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'cancellation window closed'));
    }

    public function test_reschedule_shows_slots_and_posts_the_new_time(): void
    {
        $day = now('Africa/Lagos')->addDays(5)->startOfDay();
        $slot = ['start' => $day->copy()->setTime(9, 0)->utc()->format('Y-m-d\TH:i:s\Z'), 'end' => $day->copy()->setTime(10, 0)->utc()->format('Y-m-d\TH:i:s\Z'), 'available' => true, 'remainingCapacity' => 1, 'price' => '5000.0000'];
        $this->fakeApi([
            'bookings/'.self::BOOKING.'/reschedule' => Http::response($this->booking(['status' => 'CONFIRMED'])),
            'bookings/resources/'.self::COURT.'/availability*' => Http::response(['resourceId' => self::COURT, 'slots' => [$slot]]),
            'bookings/'.self::BOOKING => Http::response($this->booking(['status' => 'CONFIRMED'])),
        ]);

        $this->signIn()->get('/account/bookings/'.self::BOOKING.'/reschedule?date='.$day->format('Y-m-d'))->assertOk()->assertSee('09:00');
        $this->post('/account/bookings/'.self::BOOKING.'/reschedule', ['slot' => $slot['start'].'|'.$slot['end'], '_submission' => (string) Str::uuid()])
            ->assertRedirect(route('account.bookings.show', self::BOOKING));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/reschedule') && $r['start'] === $slot['start'] && $r['end'] === $slot['end'] && $r->hasHeader('If-Match', '"3"'));
    }

    public function test_reschedule_conflict_409_is_friendly(): void
    {
        $this->fakeApi([
            'bookings/'.self::BOOKING.'/reschedule' => $this->problemResponse(409, 'slot_unavailable'),
            'bookings/'.self::BOOKING => Http::response($this->booking(['status' => 'CONFIRMED'])),
        ]);

        $this->signIn()->post('/account/bookings/'.self::BOOKING.'/reschedule', ['slot' => '2026-10-01T09:00:00Z|2026-10-01T10:00:00Z', '_submission' => (string) Str::uuid()])
            ->assertRedirect(route('account.bookings.reschedule', self::BOOKING))->assertSessionHas('error', fn ($m) => str_contains($m, 'taken'));
    }

    public function test_unknown_or_foreign_booking_is_404(): void
    {
        $this->fakeApi(['bookings/*' => $this->problemResponse(404, 'not_found')]);

        $this->signIn()->get('/account/bookings/'.self::BOOKING)->assertNotFound();
    }
}

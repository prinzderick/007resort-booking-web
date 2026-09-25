<?php

namespace Tests\Feature;

use App\Services\Social\SocialProviders;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\ApiTestCase;

class SocialLoginTest extends ApiTestCase
{
    private const LOGIN = 'public/customers/social/login';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'social.fake' => true,
            'social.enabled' => ['google', 'facebook'],
            'app.url' => 'https://007resorts.test',
        ]);
    }

    /** @return array<string, mixed> */
    private function apiSession(array $o = []): array
    {
        return $o + [
            'accessToken' => 'r7c_tok_social', 'refreshToken' => 'r7x_ref', 'tokenType' => 'Bearer',
            'customer' => ['id' => 'c-1', 'name' => 'Amaka Okafor', 'email' => 'amaka.okafor@example.test', 'phone' => '+2348031234567', 'emailVerified' => true],
            'isNewCustomer' => false, 'linkedExisting' => false, 'needsProfileCompletion' => false, 'missing' => [], 'recommended' => [], 'emailSuggestion' => null,
        ];
    }

    private function providersOk(array $enabled = ['google', 'facebook']): array
    {
        return ['public/customers/social/providers' => Http::response(['providers' => array_map(fn ($p) => ['id' => $p, 'enabled' => true, 'emailTrusted' => $p === 'google'], $enabled)])];
    }

    /** @return list<Request> */
    private function sent(string $method, string $pathContains): array
    {
        return array_values(array_map(fn ($pair) => $pair[0], parent::sentTo($method, $pathContains)));
    }

    /** Start the flow at /redirect and return the state the (fake) provider will echo back. */
    private function start(string $provider = 'google', string $query = ''): string
    {
        $res = $this->get("/auth/{$provider}/redirect".$query)->assertRedirect();
        parse_str((string) parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);

        return (string) $q['state'];
    }

    private function cb(string $code, string $state, string $provider = 'google')
    {
        return $this->get("/auth/{$provider}/callback?".http_build_query(['code' => $code, 'state' => $state]));
    }

    // ---- buttons ---------------------------------------------------------------------------------------------

    public function test_buttons_show_on_login_and_register_when_configured_and_enabled_in_the_api(): void
    {
        $this->fakeApi($this->providersOk());

        foreach (['/login', '/register'] as $page) {
            $this->get($page)->assertOk()
                ->assertSee('Continue with Google')->assertSee('Continue with Facebook')
                ->assertSee('/auth/google/redirect', false)->assertSee('or use your email');
        }
    }

    public function test_buttons_are_hidden_for_providers_the_api_has_not_enabled(): void
    {
        $this->fakeApi($this->providersOk(['google']));

        $this->get('/login')->assertOk()->assertSee('Continue with Google')->assertDontSee('Continue with Facebook');
    }

    public function test_buttons_are_hidden_when_the_api_cannot_say_which_providers_are_enabled(): void
    {
        $this->fakeApi(['public/customers/social/providers' => Http::response($this->problem(503, 'capability_disabled'), 503)]);

        $this->get('/login')->assertOk()->assertDontSee('Continue with')->assertDontSee('or use your email')->assertSee('Sign in');
    }

    public function test_buttons_are_hidden_for_providers_without_credentials_when_not_faking(): void
    {
        config(['social.fake' => false, 'social.providers.google.client_id' => 'gid', 'social.providers.google.client_secret' => 'gsecret', 'social.providers.facebook.client_id' => null]);
        $this->fakeApi($this->providersOk());

        $this->get('/login')->assertSee('Continue with Google')->assertDontSee('Continue with Facebook');
    }

    public function test_provider_missing_from_the_env_list_is_not_offered(): void
    {
        config(['social.enabled' => ['facebook']]);
        $this->fakeApi($this->providersOk());

        $this->get('/login')->assertSee('Continue with Facebook')->assertDontSee('Continue with Google');
    }

    public function test_no_buttons_and_no_flow_when_nothing_is_configured(): void
    {
        config(['social.fake' => false, 'social.providers.google.client_id' => null, 'social.providers.facebook.client_id' => null]);
        $this->fakeApi($this->providersOk());

        $this->get('/login')->assertDontSee('Continue with');
        $this->get('/auth/google/redirect')->assertRedirect(route('login'))->assertSessionHas('error');
        $this->get('/auth/nope/redirect')->assertNotFound();
    }

    // ---- production forbids the fake provider -------------------------------------------------------------------

    public function test_fake_mode_is_ignored_in_production(): void
    {
        $this->app['env'] = 'production';
        Log::spy();
        $this->fakeApi($this->providersOk());

        $this->assertFalse(app(SocialProviders::class)->fakeEnabled());
        $this->get('/auth/fake/google/authorize?state=x')->assertNotFound();
        // No credentials + fake ignored = no buttons at all.
        $this->get('/login')->assertDontSee('Continue with Google');
        Log::shouldHaveReceived('warning')->with('SOCIAL_FAKE is set but ignored in production');
    }

    public function test_fake_screen_works_outside_production(): void
    {
        $this->get('/auth/fake/google/authorize?state=abc')->assertOk()->assertSee('New customer')->assertSee('Fake');
    }

    // ---- redirect to a real provider (no credentials needed to inspect the URL) -----------------------------------

    public function test_real_google_redirect_uses_config_url_state_and_pkce_never_request_headers(): void
    {
        config(['social.fake' => false, 'social.providers.google.client_id' => 'gid-123', 'social.providers.google.client_secret' => 'gsecret']);
        $this->fakeApi($this->providersOk());

        $res = $this->withHeaders(['X-Forwarded-Host' => 'evil.test', 'X-Forwarded-Proto' => 'http', 'Host' => 'evil.test'])->get('/auth/google/redirect')->assertRedirect();
        $loc = $res->headers->get('Location');
        parse_str((string) parse_url($loc, PHP_URL_QUERY), $q);

        $this->assertSame('accounts.google.com', parse_url($loc, PHP_URL_HOST));
        $this->assertSame('https://007resorts.test/auth/google/callback', $q['redirect_uri']);
        $this->assertSame('gid-123', $q['client_id']);
        $this->assertNotEmpty($q['state']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertStringContainsString('email', $q['scope']);
        $this->assertStringNotContainsString('gsecret', $loc);
    }

    public function test_real_facebook_redirect_asks_for_email_and_public_profile(): void
    {
        config(['social.fake' => false, 'social.providers.facebook.client_id' => 'fb-1', 'social.providers.facebook.client_secret' => 'fbs']);
        $this->fakeApi($this->providersOk());

        $loc = $this->get('/auth/facebook/redirect')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($loc, PHP_URL_QUERY), $q);

        $this->assertSame('www.facebook.com', parse_url($loc, PHP_URL_HOST));
        $this->assertSame('https://007resorts.test/auth/facebook/callback', $q['redirect_uri']);
        $this->assertStringContainsString('email', $q['scope']);
        $this->assertStringContainsString('public_profile', $q['scope']);
    }

    public function test_real_callback_with_a_wrong_state_is_rejected_before_anything_is_exchanged(): void
    {
        config(['social.fake' => false, 'social.providers.google.client_id' => 'g', 'social.providers.google.client_secret' => 's']);
        $this->fakeApi($this->providersOk());
        $this->get('/auth/google/redirect')->assertRedirect();

        $this->get('/auth/google/callback?code=abc&state=forged')->assertRedirect(route('login'))->assertSessionHas('error');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'googleapis.com') || str_contains($r->url(), self::LOGIN));
    }

    // ---- first-time sign up: consent, then profile ---------------------------------------------------------------------

    public function test_new_customer_gets_the_consent_step_then_is_signed_in_and_returned_to_where_they_were(): void
    {
        $this->fakeApi($this->providersOk() + [
            self::LOGIN => Http::sequence()
                ->push($this->problem(422, 'terms_not_accepted'), 422, ['Content-Type' => 'application/problem+json'])
                ->push($this->apiSession(['isNewCustomer' => true]), 201),
            'newsletter*' => Http::response([]),
            'public/cms/subscribers' => Http::response(['status' => 'CHECK_EMAIL'], 202),
        ]);

        $state = $this->start('google', '?return_to=/book/sports/'.self::COURT.'?date=2026-09-30');
        $this->cb('new', $state)->assertRedirect(route('social.consent'));

        $this->get('/auth/consent')->assertOk()->assertSee('Terms')->assertSee('Privacy Policy')->assertSee('amaka.okafor@example.test')->assertSee('Optional');
        $this->post('/auth/consent', [])->assertSessionHasErrors('terms');
        Http::assertSentCount(2); // providers list + first login attempt only: nothing was created without consent

        $before = session()->getId();
        $this->post('/auth/consent', ['terms' => '1', 'marketing' => '1'])->assertRedirect('/book/sports/'.self::COURT.'?date=2026-09-30');

        $this->assertNotSame($before, session()->getId(), 'session id must rotate on sign-in');
        $this->assertSame('r7c_tok_social', session('r007.api_token'));

        $logins = $this->sent('POST', self::LOGIN);
        $this->assertCount(2, $logins);
        $this->assertFalse($logins[0]['termsAccepted']);
        $this->assertTrue($logins[1]['termsAccepted']);
        $this->assertTrue($logins[1]['marketingConsent']);
        $this->assertSame('google', $logins[1]['provider']);
        $this->assertSame('fake-google-amaka', $logins[1]['providerUserId']);
        $this->assertTrue($logins[1]['emailVerified']);
        $this->assertTrue($logins[1]->hasHeader('Authorization', 'Bearer svc-test-token'), 'social endpoints use the SERVICE token');
        $this->assertArrayNotHasKey('accessToken', $logins[1]->data());
    }

    public function test_newsletter_is_only_subscribed_when_the_box_is_ticked(): void
    {
        $this->fakeApi($this->providersOk() + [
            self::LOGIN => Http::sequence()->push($this->problem(422, 'terms_not_accepted'), 422)->push($this->apiSession(['isNewCustomer' => true]), 201),
            'public/cms/subscribers' => Http::response(['status' => 'CHECK_EMAIL'], 202),
        ]);
        $this->cb('new', $this->start());
        $this->post('/auth/consent', ['terms' => '1'])->assertRedirect();

        $this->assertSame([], $this->sent('POST', 'subscribers'));
    }

    public function test_marketing_optin_subscribes_through_the_existing_newsletter_api_with_consent_text(): void
    {
        config(['cms.driver' => 'http']);
        $this->fakeApi($this->providersOk() + [
            self::LOGIN => Http::sequence()->push($this->problem(422, 'terms_not_accepted'), 422)->push($this->apiSession(['isNewCustomer' => true]), 201),
            'public/cms/subscribers' => Http::response(['status' => 'CHECK_EMAIL'], 202),
        ]);
        $this->cb('new', $this->start());
        $this->post('/auth/consent', ['terms' => '1', 'marketing' => '1'])->assertRedirect();

        $sub = $this->sent('POST', 'subscribers');
        $this->assertCount(1, $sub);
        $this->assertSame('amaka.okafor@example.test', $sub[0]['email']);
        $this->assertTrue($sub[0]['consent']);
    }

    public function test_new_customer_without_a_phone_is_offered_the_profile_step_with_nigerian_validation(): void
    {
        $this->fakeApi($this->providersOk() + [
            self::LOGIN => Http::response($this->apiSession(['isNewCustomer' => true, 'recommended' => ['phone'], 'customer' => ['id' => 'c-1', 'name' => 'Amaka', 'email' => 'a@example.test', 'phone' => null, 'emailVerified' => true]]), 201),
            'customer/me' => Http::response(['id' => 'c-1', 'name' => 'Amaka', 'email' => 'a@example.test', 'phone' => '+2348031234567', 'emailVerified' => true]),
        ]);
        $this->cb('new', $this->start('google', '?return_to=/pool'))->assertRedirect(route('social.complete'));

        $this->get('/auth/complete')->assertOk()->assertSee('Phone (optional)')->assertDontSee('Full name');
        $this->post('/auth/complete', ['phone' => '12345'])->assertSessionHasErrors('phone');
        $this->post('/auth/complete', ['phone' => '0803 123 4567'])->assertRedirect('/pool');

        $patch = $this->sent('PATCH', 'customer/me');
        $this->assertSame('+2348031234567', $patch[0]['phone']);
    }

    public function test_provider_without_email_collects_and_verifies_an_email_before_returning(): void
    {
        $this->fakeApi($this->providersOk() + [
            self::LOGIN => Http::response($this->apiSession([
                'isNewCustomer' => true, 'needsProfileCompletion' => true, 'missing' => ['email'], 'emailSuggestion' => 'maybe@example.test',
                'customer' => ['id' => 'c-2', 'name' => 'Google Friend', 'email' => null, 'phone' => null, 'emailVerified' => false],
            ]), 201),
            'customer/me/email' => Http::response([], 202),
            'customer/me/email/verify' => Http::response(['id' => 'c-2', 'name' => 'Google Friend', 'email' => 'me@example.test', 'emailVerified' => true]),
            'customer/me' => Http::response(['id' => 'c-2', 'name' => 'Google Friend', 'email' => 'me@example.test', 'emailVerified' => true]),
        ]);
        $this->cb('noemail', $this->start('google', '?return_to=/memberships'))->assertRedirect(route('social.complete'));

        $login = $this->sent('POST', self::LOGIN)[0];
        $this->assertArrayNotHasKey('email', $login->data());
        $this->assertFalse($login['emailVerified'], 'never invent verified=true');

        $this->get('/auth/complete')->assertOk()->assertSee('did not share a verified email')->assertSee('maybe@example.test', false);
        $this->post('/auth/complete', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->post('/auth/complete', ['email' => 'me@example.test'])->assertRedirect(route('social.complete'));
        $this->get('/auth/complete')->assertSee('6-digit code')->assertSee('m•••@example.test');

        $this->post('/auth/complete/verify', ['code' => '123456'])->assertRedirect('/memberships');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'customer/me/email/verify') && $r['code'] === '123456' && $r->hasHeader('Authorization', 'Bearer r7c_tok_social'));
        $this->assertNull(session('social.complete'));
    }

    public function test_bad_email_code_shows_the_api_message_and_stays_on_the_code_step(): void
    {
        $this->fakeApi($this->providersOk() + [
            self::LOGIN => Http::response($this->apiSession(['isNewCustomer' => true, 'missing' => ['email'], 'customer' => ['id' => 'c-2', 'name' => 'X', 'email' => null, 'emailVerified' => false]]), 201),
            'customer/me/email/verify' => $this->problemResponse(422, 'invalid_verification'),
            'customer/me/email' => Http::response([], 202),
            'customer/me' => Http::response(['id' => 'c-2', 'name' => 'X', 'email' => null, 'emailVerified' => false]),
        ]);
        $this->cb('noemail', $this->start());
        $this->post('/auth/complete', ['email' => 'me@example.test']);

        $this->post('/auth/complete/verify', ['code' => '000000'])->assertSessionHasErrors(['code' => 'That code is wrong or has expired.']);
    }

    public function test_skipping_the_profile_step_returns_to_the_page_and_the_account_page_nudges_for_an_email(): void
    {
        $this->fakeApi($this->providersOk() + [
            self::LOGIN => Http::response($this->apiSession(['isNewCustomer' => true, 'missing' => ['email'], 'customer' => ['id' => 'c-2', 'name' => 'X', 'email' => null, 'emailVerified' => false]]), 201),
            'customer/bookings*' => $this->page([]), 'customer/memberships*' => $this->page([]),
            'customer/me/identities' => Http::response(['items' => [], 'hasPassword' => false, 'canUnlink' => false]),
        ]);
        $this->cb('noemail', $this->start('google', '?return_to=/pool'));

        $this->post('/auth/complete/skip')->assertRedirect('/pool');
        $this->get('/account')->assertSee('Add and verify your email');
    }

    // ---- returning users, denial, tampering, replay ---------------------------------------------------------------------

    public function test_returning_customer_is_signed_in_straight_away_and_sent_back(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->apiSession(), 200)]);

        $state = $this->start('google', '?return_to=/checkout/abc');
        $this->cb('returning', $state)->assertRedirect('/checkout/abc')->assertSessionHas('status');

        $this->assertSame('r7c_tok_social', session('r007.api_token'));
        $this->assertSame('Amaka Okafor', session('r007.customer.name'));
    }

    public function test_guest_bounced_from_a_protected_page_returns_there_after_social_sign_in(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->apiSession(), 200)]);

        $this->get('/account/bookings')->assertRedirect(route('login'));
        $state = $this->start(); // no explicit return_to: the intended URL the middleware stored is used
        $res = $this->cb('returning', $state);
        $this->assertStringEndsWith('/account/bookings', $res->headers->get('Location'));
    }

    public function test_user_denying_consent_gets_a_friendly_message_and_nothing_reaches_the_api(): void
    {
        $this->fakeApi($this->providersOk());
        $state = $this->start('facebook');

        $this->get('/auth/facebook/callback?error=access_denied&error_reason=user_denied&state='.$state)
            ->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertStringContainsString('cancelled', session('error'));
        $this->assertSame([], $this->sent('POST', self::LOGIN));
        $this->assertNull(session('r007.api_token'));
    }

    public function test_denial_started_from_the_sign_up_page_goes_back_to_sign_up(): void
    {
        $this->fakeApi($this->providersOk());
        $this->start('google', '?from=register');

        $this->get('/auth/google/callback?error=access_denied')->assertRedirect(route('register'));
    }

    public function test_other_provider_errors_are_reported_calmly(): void
    {
        $this->fakeApi($this->providersOk());
        $this->start();

        $this->get('/auth/google/callback?error=server_error&error_description=secret+details')->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertStringNotContainsString('secret', session('error'));
    }

    public function test_tampered_missing_and_replayed_state_are_refused(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->apiSession(), 200)]);

        // tampered
        $state = $this->start();
        $this->cb('returning', 'tampered')->assertRedirect(route('login'))->assertSessionHas('error');
        // ...and the genuine state was consumed by that failed attempt, so it cannot be replayed either
        $this->cb('returning', $state)->assertRedirect(route('login'))->assertSessionHas('error');
        // missing
        $this->start();
        $this->get('/auth/google/callback?code=returning')->assertRedirect(route('login'))->assertSessionHas('error');
        // never started
        $this->flushSession();
        $this->cb('returning', 'whatever')->assertRedirect(route('login'));
        $this->assertSame([], $this->sent('POST', self::LOGIN));

        // a good state works once, then fails when replayed
        $good = $this->start();
        $this->cb('returning', $good)->assertRedirect();
        $this->assertNotNull(session('r007.api_token'));
        $this->cb('returning', $good)->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertCount(1, $this->sent('POST', self::LOGIN));
    }

    public function test_open_redirect_attempts_in_return_to_are_ignored(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->apiSession(), 200)]);

        foreach (['https://evil.test/x', '//evil.test/x', '/\\evil.test', 'javascript:alert(1)', '/%0d%0aLocation:https://evil.test', 'http://localhost@evil.test/', '/auth/google/redirect', '/login'] as $bad) {
            $state = $this->start('google', '?return_to='.rawurlencode($bad));
            $res = $this->cb('returning', $state);
            $res->assertRedirect(route('account'));
            $this->flushSession();
        }
    }

    public function test_callback_is_rate_limited(): void
    {
        $this->fakeApi($this->providersOk());
        for ($i = 0; $i < 60; $i++) {
            $this->get('/auth/google/callback?error=access_denied');
        }

        $this->get('/auth/google/callback?error=access_denied')->assertStatus(429);
    }

    public function test_logs_contain_no_personal_data_or_tokens(): void
    {
        $seen = [];
        app('events')->listen(MessageLogged::class, function (MessageLogged $e) use (&$seen) {
            $seen[] = json_encode([$e->message, $e->context]);
        });
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->apiSession(['isNewCustomer' => true]), 201)]);
        $this->cb('new', $this->start());
        $this->get('/auth/google/callback?error=access_denied');

        $this->assertNotEmpty($seen);
        foreach ($seen as $line) {
            $this->assertStringNotContainsString('amaka', strtolower($line));
            $this->assertStringNotContainsString('r7c_', $line);
            $this->assertStringNotContainsString('fake-google', $line);
            $this->assertStringNotContainsString('@', $line);
        }
    }

    // ---- account linking --------------------------------------------------------------------------------------------------

    private function linkRequired(): array
    {
        return $this->problem(409, 'account_link_requires_confirmation') + ['meta' => ['confirmation' => ['method' => 'email_code', 'maskedEmail' => 'd***@007resort.test', 'expiresInSeconds' => 1800]]];
    }

    public function test_existing_unverified_account_requires_the_emailed_code_and_is_never_silently_merged(): void
    {
        $this->fakeApi($this->providersOk() + [
            self::LOGIN => Http::response($this->linkRequired(), 409, ['Content-Type' => 'application/problem+json']),
            'public/customers/social/link/confirm' => Http::sequence()->push($this->problem(422, 'invalid_link_confirmation'), 422)->push($this->apiSession(['linkedExisting' => true]), 200),
        ]);
        $this->cb('linkrequired', $this->start('google', '?return_to=/pool'))->assertRedirect(route('social.link'));

        $this->assertNull(session('r007.api_token'), 'no session until the mailbox owner confirms');
        $this->assertTrue($this->sent('POST', self::LOGIN)[0]['emailVerified'], 'passed exactly as Google reported it; the API decides what to trust');

        $this->get('/auth/link')->assertOk()->assertSee('d***@007resort.test')->assertSee('already exists')->assertSee('30 minutes');
        $this->post('/auth/link', ['code' => '111111'])->assertSessionHasErrors('code');
        $this->post('/auth/link', ['code' => '123 456'])->assertRedirect('/pool');

        $this->assertSame('r7c_tok_social', session('r007.api_token'));
        $confirm = $this->sent('POST', 'link/confirm');
        $this->assertSame('demo@007resort.test', $confirm[1]['email']);
        $this->assertSame('123456', $confirm[1]['code']);
        $this->assertNull(session('social.link'));
    }

    public function test_link_page_needs_a_pending_link_and_can_be_cancelled(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->linkRequired(), 409)]);

        $this->get('/auth/link')->assertRedirect(route('login'));
        $this->cb('linkrequired', $this->start('google', '?from=register'));
        $this->post('/auth/cancel')->assertRedirect(route('register'));
        $this->get('/auth/link')->assertRedirect(route('login'));
    }

    public function test_resend_asks_the_api_for_a_new_code(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->linkRequired(), 409)]);
        $this->cb('linkrequired', $this->start());

        $this->post('/auth/link/resend')->assertRedirect(route('social.link'))->assertSessionHas('status', 'We sent a new code.');
        $this->assertCount(2, $this->sent('POST', self::LOGIN));
    }

    public function test_identity_conflict_is_explained(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->problem(409, 'identity_conflict'), 409)]);
        $this->cb('returning', $this->start())->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertStringContainsString('different account', session('error'));
    }

    public function test_locked_account_is_explained(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->problem(423, 'account_locked'), 423)]);
        $this->cb('returning', $this->start())->assertSessionHas('error');
        $this->assertStringContainsString('locked', session('error'));
    }

    public function test_api_outage_during_login_shows_a_calm_message(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->problem(503, 'capability_disabled'), 503)]);

        $this->cb('returning', $this->start())->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertNull(session('r007.api_token'));
    }

    // ---- facebook: email is passed as reported (false) -------------------------------------------------------------------------

    public function test_facebook_email_is_never_claimed_verified(): void
    {
        $this->fakeApi($this->providersOk() + [self::LOGIN => Http::response($this->apiSession(), 200)]);
        $this->cb('returning', $this->start('facebook'), 'facebook');

        $login = $this->sent('POST', self::LOGIN)[0];
        $this->assertSame('facebook', $login['provider']);
        $this->assertFalse($login['emailVerified']);
    }

    // ---- account page: sign-in methods ---------------------------------------------------------------------------------------------

    private function methodsApi(array $identities, bool $hasPassword, bool $canUnlink, array $extra = []): void
    {
        $this->fakeApi($this->providersOk() + $extra + [
            'customer/bookings*' => $this->page([]), 'customer/memberships*' => $this->page([]),
            'customer/me/identities' => Http::response(['items' => $identities, 'hasPassword' => $hasPassword, 'canUnlink' => $canUnlink]),
        ]);
    }

    private function identity(string $provider = 'google', array $o = []): array
    {
        return $o + ['id' => '0192f6a0-0000-7000-8000-00000000aa01', 'provider' => $provider, 'email' => 'ada@example.com', 'emailVerified' => true, 'avatarUrl' => 'https://lh3.googleusercontent.com/a/photo=s96-c', 'linkedAt' => '2026-09-20T10:00:00Z'];
    }

    public function test_sign_in_methods_lists_linked_providers_with_a_safe_avatar_and_offers_the_others(): void
    {
        $this->methodsApi([$this->identity(), $this->identity('facebook', ['id' => '0192f6a0-0000-7000-8000-00000000aa02', 'avatarUrl' => 'http://evil.test/a.png'])], true, true);
        config(['social.enabled' => ['google', 'facebook']]);

        $res = $this->signIn()->get('/account')->assertOk()->assertSee('Sign-in methods')->assertSee('Google')->assertSee('Facebook')->assertSee('Disconnect')
            ->assertSee('referrerpolicy="no-referrer"', false)->assertSee('https://lh3.googleusercontent.com/a/photo=s96-c', false);
        $res->assertDontSee('http://evil.test/a.png', false)->assertDontSee('Connect another way to sign in');
    }

    public function test_connect_offer_lists_only_missing_providers(): void
    {
        $this->methodsApi([$this->identity()], true, true);

        $this->signIn()->get('/account')->assertSee('Connect Facebook')->assertDontSee('Connect Google');
    }

    public function test_last_sign_in_method_cannot_be_disconnected_and_a_password_is_offered(): void
    {
        $this->methodsApi([$this->identity()], false, false);

        $res = $this->signIn()->get('/account')->assertOk();
        $res->assertSee('cannot be disconnected')->assertSee('Set a password');
        $res->assertDontSee('/account/sign-in/0192f6a0-0000-7000-8000-00000000aa01/disconnect', false);
        $res->assertSee('disabled', false);
    }

    public function test_disconnect_calls_the_api_and_the_last_method_guard_is_enforced_server_side_too(): void
    {
        $id = '0192f6a0-0000-7000-8000-00000000aa01';
        $this->fakeApi(["customer/me/identities/{$id}" => Http::sequence()->push(null, 204)->push($this->problem(409, 'last_login_method'), 409)]);

        $this->signIn()->post("/account/sign-in/{$id}/disconnect")->assertRedirect(route('account').'#sign-in')->assertSessionHas('status');
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && $r->hasHeader('Authorization', 'Bearer cust-token'));

        $this->signIn()->post("/account/sign-in/{$id}/disconnect")->assertSessionHas('error');
        $this->assertStringContainsString('at least one way to sign in', session('error'));
    }

    public function test_disconnect_needs_a_signed_in_customer_and_a_sane_id(): void
    {
        $this->fakeApi([]);
        $this->post('/account/sign-in/0192f6a0-0000-7000-8000-00000000aa01/disconnect')->assertRedirect(route('login'));
        $this->signIn()->post('/account/sign-in/not-an-id/disconnect')->assertNotFound();
    }

    public function test_set_password_validates_and_calls_the_api(): void
    {
        $this->fakeApi(['customer/me/password' => Http::response([], 204)]);

        $this->signIn()->post('/account/sign-in/password', ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->signIn()->post('/account/sign-in/password', ['password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery'])->assertSessionHas('status');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'customer/me/password') && $r['password'] === 'correct-horse-battery' && ! isset($r['currentPassword']));
    }

    public function test_connect_provider_links_it_with_service_token_plus_customer_token(): void
    {
        $this->fakeApi($this->providersOk() + ['customer/me/social/link' => Http::response(['identity' => $this->identity('facebook')], 201)]);

        $this->signIn();
        $state = $this->start('facebook', '?intent=connect');
        $this->cb('returning', $state, 'facebook')->assertRedirect(route('account').'#sign-in')->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'customer/me/social/link')
            && $r->hasHeader('Authorization', 'Bearer svc-test-token') && $r->hasHeader('X-Customer-Token', 'cust-token')
            && $r['provider'] === 'facebook' && $r['emailVerified'] === false);
    }

    public function test_connecting_a_provider_owned_by_someone_else_is_refused_politely(): void
    {
        $this->fakeApi($this->providersOk() + ['customer/me/social/link' => Http::response($this->problem(409, 'identity_already_linked'), 409)]);

        $this->signIn();
        $state = $this->start('google', '?intent=connect');
        $this->cb('returning', $state)->assertRedirect(route('account').'#sign-in')->assertSessionHas('error');
        $this->assertStringContainsString('already connected', session('error'));
    }

    // ---- CSP / avatars ----------------------------------------------------------------------------------------------------------------

    public function test_csp_allows_provider_avatar_hosts_only_and_no_inline_script_or_new_script_sources(): void
    {
        config(['app.debug' => false]);
        $this->fakeApi($this->providersOk());

        $csp = $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://lh3.googleusercontent.com', $csp);
        $this->assertStringContainsString('https://*.fbcdn.net', $csp);
        preg_match('/script-src ([^;]+)/', $csp, $m);
        $this->assertMatchesRegularExpression("/^'self' 'nonce-[^']+'$/", $m[1]);
        $this->assertStringNotContainsString('unsafe-inline', $m[1]);
    }
}

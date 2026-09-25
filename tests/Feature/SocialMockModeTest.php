<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The whole social journey against the in-process Mock API + fake provider (what the README demo uses). */
class SocialMockModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['r007.mock' => true, 'social.fake' => true, 'social.enabled' => ['google', 'facebook']]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function go(string $provider, string $persona): TestResponse
    {
        $res = $this->get("/auth/{$provider}/redirect");
        parse_str((string) parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);

        return $this->get("/auth/{$provider}/callback?".http_build_query(['code' => $persona, 'state' => $q['state']]));
    }

    public function test_new_then_returning_google_customer_and_disconnect_guard(): void
    {
        $this->get('/login')->assertSee('Continue with Google');
        $this->go('google', 'new')->assertRedirect(route('social.consent'));
        $this->post('/auth/consent', ['terms' => '1'])->assertRedirect(route('social.complete'));
        $this->post('/auth/complete/skip')->assertRedirect(route('account'));

        $this->get('/account')->assertOk()->assertSee('Sign-in methods')->assertSee('amaka.okafor@example.test')->assertSee('cannot be disconnected');
        $this->post('/logout');

        // Returning: no consent, no profile step, straight in.
        $this->go('google', 'returning')->assertRedirect(route('account'));
        $this->get('/account')->assertSee('Hello, Amaka');
    }

    public function test_provider_without_email_completes_with_the_emailed_code(): void
    {
        $this->go('google', 'noemail')->assertRedirect(route('social.consent'));
        $this->post('/auth/consent', ['terms' => '1'])->assertRedirect(route('social.complete'));
        $this->post('/auth/complete', ['email' => 'friend@example.test', 'phone' => '08031234567'])->assertRedirect(route('social.complete'));
        $this->post('/auth/complete/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/auth/complete/verify', ['code' => '123456'])->assertRedirect(route('account'));

        $this->get('/account')->assertDontSee('Add and verify your email');
    }

    public function test_existing_unverified_email_needs_the_code_then_links(): void
    {
        $this->go('google', 'linkrequired')->assertRedirect(route('social.link'));
        $this->get('/auth/link')->assertSee('d***@007resort.test');
        $this->post('/auth/link', ['code' => '654321'])->assertSessionHasErrors('code');
        $this->post('/auth/link', ['code' => '123456'])->assertRedirect(route('account'));
        $this->assertNotNull(session('r007.api_token'));
    }

    public function test_facebook_new_customer_gets_an_email_suggestion_because_facebook_emails_are_not_trusted(): void
    {
        $this->go('facebook', 'new')->assertRedirect(route('social.consent'));
        $this->post('/auth/consent', ['terms' => '1'])->assertRedirect(route('social.complete'));
        $this->get('/auth/complete')->assertSee('amaka.okafor@example.test', false)->assertSee('did not share a verified email');
    }

    public function test_connect_disconnect_and_password_from_the_account_page(): void
    {
        $this->go('google', 'new');
        $this->post('/auth/consent', ['terms' => '1']);
        $this->post('/auth/complete/skip');

        $this->get('/auth/facebook/redirect?intent=connect');
        $loc = $this->get('/auth/facebook/redirect?intent=connect')->headers->get('Location');
        parse_str((string) parse_url($loc, PHP_URL_QUERY), $q);
        $this->get('/auth/facebook/callback?'.http_build_query(['code' => 'returning', 'state' => $q['state']]))->assertRedirect(route('account').'#sign-in');

        $page = $this->get('/account')->assertSee('Facebook')->assertSee('Disconnect');
        preg_match_all('#/account/sign-in/([0-9a-f-]{36})/disconnect#', $page->getContent(), $m);
        $this->assertCount(2, $m[1]);
        $this->post('/account/sign-in/'.$m[1][1].'/disconnect')->assertSessionHas('status');
        $this->get('/account')->assertSee('cannot be disconnected');

        $this->post('/account/sign-in/password', ['password' => 'long-enough-password', 'password_confirmation' => 'long-enough-password'])->assertSessionHas('status');
        $this->get('/account')->assertSee('Change password');
    }
}

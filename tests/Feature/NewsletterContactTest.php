<?php

namespace Tests\Feature;

use App\Services\Cms\CmsClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\ApiTestCase;

class NewsletterContactTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_subscribe_returns_json_and_never_leaks_existence(): void
    {
        $this->postJson('/newsletter', ['email' => 'ada@example.com', 'source' => 'footer'])->assertOk()->assertJson(['ok' => true])->assertJsonPath('message', fn ($m) => str_contains($m, 'confirm'));
        $this->postJson('/newsletter', ['email' => 'ada@example.com', 'source' => 'footer'])->assertOk()->assertJson(['ok' => true]); // same answer again
        $this->postJson('/newsletter', ['email' => 'not-an-email'])->assertStatus(422);
    }

    public function test_subscribe_without_js_redirects_back_with_a_message(): void
    {
        $this->from('/blog')->post('/newsletter', ['email' => 'bee@example.com', 'source' => 'blog'])->assertRedirect('/blog')->assertSessionHas('subscribe_status');
    }

    public function test_honeypot_submissions_look_successful_but_are_not_subscribed(): void
    {
        $this->postJson('/newsletter', ['email' => 'bot@example.com', 'website' => 'http://spam.test'])->assertOk()->assertJson(['ok' => true]);
        $this->assertNull(Cache::get('cms.fixture.last_token'));
    }

    public function test_double_opt_in_confirm_then_unsubscribe(): void
    {
        $this->postJson('/newsletter', ['email' => 'cee@example.com'])->assertOk();
        $token = Cache::get('cms.fixture.last_token');
        $this->assertNotEmpty($token);

        // GET only previews: mail scanners prefetching the link must not confirm anything
        $this->get('/newsletter/confirm?token='.$token)->assertOk()->assertSee('One tap to confirm')->assertSee('noindex', false);
        $this->assertSame('PENDING', Cache::get('cms.fixture.subs')['cee@example.com']['status']);

        $this->post('/newsletter/confirm', ['token' => $token])->assertOk()->assertSee('You are on the list');
        $this->assertSame('CONFIRMED', Cache::get('cms.fixture.subs')['cee@example.com']['status']);
        $this->get('/newsletter/confirm?token='.$token)->assertSee('You are on the list');

        $this->get('/newsletter/unsubscribe?token='.$token)->assertOk()->assertSee('Leave the list');
        $this->post('/newsletter/unsubscribe', ['token' => $token])->assertOk()->assertSee('You have been unsubscribed');
        $this->assertSame('UNSUBSCRIBED', Cache::get('cms.fixture.subs')['cee@example.com']['status']);
    }

    public function test_invalid_tokens_show_a_friendly_page(): void
    {
        $this->get('/newsletter/confirm?token=short')->assertOk()->assertSee('does not look right');
        $this->get('/newsletter/confirm?token='.str_repeat('a', 24))->assertOk()->assertSee('does not look right');
        $this->post('/newsletter/unsubscribe', ['token' => str_repeat('b', 24)])->assertOk()->assertSee('does not look right');
        $this->get('/newsletter/unsubscribe')->assertOk()->assertSee('does not look right');
    }

    public function test_subscribe_forms_are_on_footer_blog_events_and_popup(): void
    {
        $html = $this->get('/blog')->getContent();
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'data-subscribe-form')); // footer + inline + popup
        $this->assertStringContainsString('name="website"', $html); // honeypot
        $this->assertStringContainsString('data-subpop', $html);
        config(['cms.subscribe_popup' => false]);
        $this->assertStringNotContainsString('data-subpop', $this->get('/blog')->getContent());
    }

    public function test_http_subscribe_sends_consent_source_and_visitor_ip(): void
    {
        config(['cms.driver' => 'http', 'cms.path' => 'public/cms']);
        $this->app->forgetInstance(CmsClient::class);
        Http::fake([$this->url('public/cms/subscribers') => Http::response(['status' => 'CHECK_EMAIL']), $this->url('public/site') => Http::response($this->siteBody()), '*' => Http::response([], 404)]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/newsletter', ['email' => 'dee@example.com', 'source' => 'popup'])->assertOk();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'public/cms/subscribers') && $r['email'] === 'dee@example.com' && $r['consent'] === true && $r['source'] === 'popup' && $r['website'] === ''
            && $r->hasHeader('X-Client-IP', '203.0.113.9') && $r->hasHeader('Idempotency-Key') && $r->hasHeader('Authorization', 'Bearer svc-test-token'));
    }

    public function test_http_subscribe_maps_rate_limit_and_outage(): void
    {
        config(['cms.driver' => 'http', 'cms.path' => 'public/cms']);
        $this->app->forgetInstance(CmsClient::class);
        Http::fake([$this->url('public/cms/subscribers') => Http::sequence()->push(['code' => 'rate_limited', 'title' => 'Slow down'], 429, ['Retry-After' => '60'])->push(['code' => 'boom'], 500), '*' => Http::response([], 404)]);

        $this->postJson('/newsletter', ['email' => 'e@example.com'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Too many'));
        $this->postJson('/newsletter', ['email' => 'e@example.com'])->assertStatus(503)->assertJsonPath('ok', false);
    }

    public function test_http_confirm_maps_expired_and_invalid_tokens(): void
    {
        config(['cms.driver' => 'http', 'cms.path' => 'public/cms']);
        $this->app->forgetInstance(CmsClient::class);
        Http::fake([
            $this->url('public/cms/subscribers/confirm/expiredtoken1') => Http::response(['code' => 'token_expired'], 410),
            $this->url('public/cms/subscribers/confirm/goodtoken12345') => Http::response(['valid' => true, 'status' => 'PENDING']),
            $this->url('public/cms/subscribers/confirm/*') => Http::response(['code' => 'invalid_token'], 404), $this->url('public/site') => Http::response($this->siteBody()), '*' => Http::response([], 404),
        ]);
        $this->get('/newsletter/confirm?token=expiredtoken1')->assertSee('expired');
        $this->get('/newsletter/confirm?token=goodtoken12345')->assertSee('One tap to confirm');
        $this->get('/newsletter/confirm?token=unknowntoken12')->assertSee('does not look right');
    }

    public function test_contact_form_posts_to_the_cms_and_validates(): void
    {
        $this->get('/contact')->assertOk()->assertSee('name="website"', false)->assertSee('name="_ts"', false);
        $m = [1 => Crypt::encryptString((string) (time() - 10))]; // the form was rendered 10s ago
        $this->post('/contact', ['name' => 'Ada', 'email' => 'ada@example.com', 'topic' => 'EVENTS', 'message' => 'Do you host weddings on the terrace?', '_ts' => $m[1]])
            ->assertRedirect(route('contact'))->assertSessionHas('status');
        $this->assertSame('EVENTS', Cache::get('cms.fixture.last_contact')['topic']);

        $this->post('/contact', ['name' => '', 'email' => 'bad', 'message' => 'short', '_ts' => $m[1]])->assertSessionHasErrors(['name', 'email', 'message']);
        $this->post('/contact', ['name' => 'Bot', 'email' => 'b@example.com', 'message' => 'Spam spam spam spam', 'website' => 'x', '_ts' => $m[1]])->assertSessionHasErrors('form');
    }

    public function test_contact_http_errors_are_shown_calmly(): void
    {
        config(['cms.driver' => 'http', 'cms.path' => 'public/cms']);
        $this->app->forgetInstance(CmsClient::class);
        Http::fake([$this->url('public/cms/contact') => Http::sequence()->push(['code' => 'validation_failed', 'errors' => ['message' => ['The message is too short.']]], 422)->push(['code' => 'rate_limited'], 429)->push([], 503), '*' => Http::response([], 404)]);
        $ts = Crypt::encryptString((string) (time() - 10));
        $data = ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'A perfectly fine message here.', '_ts' => $ts];

        $this->post('/contact', $data)->assertSessionHasErrors('message');
        $this->post('/contact', $data)->assertSessionHasErrors('form');
        $this->post('/contact', $data)->assertSessionHasErrors('form');
    }
}

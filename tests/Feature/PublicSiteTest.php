<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\ApiTestCase;

class PublicSiteTest extends ApiTestCase
{
    public function test_home_shows_every_facility_with_api_contact_details(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);

        $this->get('/')->assertOk()
            ->assertSee('Restaurant')->assertSee('Indoor Club')->assertSee('Beauty Spa')->assertSee('Swimming Pool')
            ->assertSee('Sports Arena')->assertSee('Bush Bar &amp; Event Centre', false)->assertSee('Salon')->assertSee('Cafe')->assertSee('Supermarket')
            ->assertSee('application/ld+json', false)
            ->assertDontSee('temporarily unavailable');
    }

    public function test_site_uses_service_token_for_public_reads(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);

        $this->get('/')->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'public/site') && $r->hasHeader('Authorization', 'Bearer svc-test-token'));
    }

    public function test_home_still_renders_when_the_api_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->get('/')->assertOk()
            ->assertSee('Swimming Pool')
            ->assertSee('Live availability is temporarily unavailable');
    }

    public function test_facility_page_shows_hours_contact_and_booking_cta(): void
    {
        $body = $this->siteBody(['sports' => ['openingHours' => 'Daily 07:00-21:00', 'phone' => '+234 800 111 2222']]);
        $this->fakeApi(['public/site' => Http::response($body)]);

        $this->get('/facilities/sports-arena')->assertOk()
            ->assertSee('Daily 07:00-21:00')->assertSee('+234 800 111 2222')->assertSee('Book now')
            ->assertSee('LocalBusiness', false);
    }

    public function test_paused_facility_shows_a_notice_for_that_facility_only(): void
    {
        $body = $this->siteBody(['spa' => ['onlineBookable' => false, 'onlineNotice' => 'Spa booking is paused while systems reconnect.']]);
        $this->fakeApi(['public/site' => Http::response($body)]);

        $this->get('/facilities/beauty-spa')->assertOk()->assertSee('Spa booking is paused while systems reconnect.')->assertDontSee('Book now');
        $this->get('/facilities/sports-arena')->assertOk()->assertSee('Book now')->assertDontSee('paused');
        $this->get('/book/beauty-spa')->assertOk()->assertSee('Spa booking is paused');
    }

    public function test_info_only_facility_offers_no_online_booking(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);

        $this->get('/facilities/restaurant')->assertOk()->assertDontSee('Book now')->assertSee('Walk in');
        $this->get('/book/restaurant')->assertNotFound();
    }

    public function test_unknown_facility_is_404(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);
        $this->get('/facilities/moon-base')->assertNotFound();
    }

    public function test_contact_page_lists_hours(): void
    {
        // Contact details and hours are CMS content now (fixtures in tests); facility pages still show the booking API's own hours.
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);
        $this->get('/contact')->assertOk()->assertSee('Otueke')->assertSee('Monday')->assertSee('7am to 11pm');
    }

    public function test_location_copy_mentions_the_federal_university_otueke(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);
        $this->get('/contact')->assertOk()->assertSee('Getting here')->assertSee('Federal University Otueke')->assertSee('Ask reception for directions');
        $this->get('/')->assertOk()->assertSee('Near the Federal University Otueke, Bayelsa State');
        $this->get('/faq')->assertOk()->assertSee('How do I get there?')->assertSee('Is the resort close to the university?');
    }

    public function test_seo_endpoints(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /account')->assertSee('sitemap.xml');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/facilities/beauty-spa');
        $this->get('/')->assertSee('<meta name="description"', false)->assertSee('rel="canonical"', false);
    }

    public function test_security_headers_are_set(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);

        $this->get('/')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Request-Id');
    }
}

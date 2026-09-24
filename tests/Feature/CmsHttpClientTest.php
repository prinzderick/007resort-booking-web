<?php

namespace Tests\Feature;

use App\Services\Cms\CachedCmsClient;
use App\Services\Cms\CmsClient;
use App\Services\Cms\CmsUnavailableException;
use App\Services\Cms\FixtureCmsClient;
use App\Services\Cms\HttpCmsClient;
use App\Services\Online\ContentService;
use App\Services\Online\SiteContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\ApiTestCase;

/** The real CMS client against a faked API that follows docs/CMS_API.md of 007resort-api. */
class CmsHttpClientTest extends ApiTestCase
{
    private function cms(string $path): string
    {
        return $this->url('public/cms/'.$path);
    }

    private function useHttpCms(int $ttl = 60): void
    {
        config(['cms.driver' => 'http', 'cms.ttl' => $ttl, 'cms.stale_ttl' => 3600, 'cms.path' => 'public/cms']);
        Cache::flush();
        foreach ([CmsClient::class, SiteContext::class, ContentService::class] as $c) {
            $this->app->forgetInstance($c);
        }
    }

    /** @return array<string, mixed> */
    private function apiFakes(): array
    {
        $media = fn (string $id) => ['id' => $id, 'url' => "https://cdn.test/$id.jpg", 'width' => 1600, 'height' => 1067, 'mimeType' => 'image/jpeg', 'alt' => 'Pool at dusk', 'credit' => null, 'dominantColor' => '#3b7a9c',
            'variants' => [['width' => 480, 'format' => 'webp', 'url' => "https://cdn.test/$id-480.webp"], ['width' => 960, 'format' => 'webp', 'url' => "https://cdn.test/$id-960.webp"]]];
        $site = ['brand' => ['name' => '007 Resort & Spa', 'tagline' => 'Live loud, swim slow.', 'logo' => null], 'contact' => ['phone' => '+234 1', 'whatsapp' => '+2341', 'email' => 'x@y.test', 'address' => 'Otueke', 'mapEmbedUrl' => null, 'lat' => null, 'lng' => null],
            'hours' => ['weekly' => array_map(fn ($d) => ['day' => $d, 'open' => '00:00', 'close' => '23:59', 'closed' => false], ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']), 'notes' => 'Always open.', 'holidays' => []],
            'social' => ['instagram' => 'https://instagram.com/x', 'facebook' => null], 'seo' => ['titleTemplate' => '%s | 007', 'defaultTitle' => 'Default title', 'defaultDescription' => 'Default description', 'ogImage' => $media('og')],
            'announcement' => ['enabled' => true, 'text' => 'Lights on till late', 'link' => '/events', 'tone' => 'PROMO'], 'booking' => ['ticketsCtaLabel' => 'Book tickets', 'bookingCtaLabel' => 'Book a court', 'membershipCtaLabel' => 'Join', 'eventsCtaLabel' => 'Get tickets'],
            'footer' => ['text' => 'Footer words', 'copyright' => '007 Resort'], 'updatedAt' => '2026-10-03T09:00:00.000Z'];
        $slide = ['id' => 's1', 'type' => 'HERO_SLIDE', 'sortOrder' => 10, 'headline' => 'Swim slow', 'subheadline' => 'Under the palms', 'media' => $media('hero'), 'ctaLabel' => 'Go', 'ctaLink' => '/pool', 'alignment' => 'LEFT'];
        $hl = ['id' => 'h1', 'type' => 'HIGHLIGHT', 'sortOrder' => 20, 'title' => 'The deep end', 'blurb' => 'Two metres of blue', 'priceFrom' => 'From NGN 2,000', 'media' => $media('hl'), 'link' => '/pool', 'category' => 'splash'];
        $home = ['sections' => [$slide, $hl], 'byType' => ['HERO_SLIDE' => [$slide], 'HIGHLIGHT' => [$hl], 'STAT' => [], 'TESTIMONIAL' => [], 'FAQ' => [], 'PARTNER' => [], 'CTA_BAND' => []], 'updatedAt' => '2026-10-03T09:00:00.000Z'];

        return [
            $this->cms('site') => Http::response($site), $this->cms('home') => Http::response($home), $this->cms('pages') => Http::response(['items' => [['slug' => 'terms', 'title' => 'Terms of use', 'showInFooter' => true]], 'nextCursor' => null]),
            $this->cms('pages/home') => Http::response(['message' => 'nf', 'code' => 'not_found'], 404), $this->cms('pages/about') => Http::response(['id' => 'p', 'slug' => 'about', 'title' => 'About us', 'subtitle' => null, 'hero' => $media('ph'), 'bodyHtml' => '<p>We are here.</p>', 'bodyMarkdown' => 'We are here.', 'seo' => ['title' => null, 'description' => 'About desc', 'ogImage' => null], 'showInFooter' => true, 'publishedAt' => '2026-10-01T00:00:00.000Z', 'updatedAt' => '2026-10-01T00:00:00.000Z']),
            $this->cms('events*') => Http::response(['items' => [], 'nextCursor' => null]), $this->cms('posts*') => Http::response(['items' => [], 'nextCursor' => null]),
            $this->cms('gallery/albums*') => Http::response(['items' => [], 'nextCursor' => null]), $this->cms('post-categories*') => Http::response(['items' => [], 'nextCursor' => null]),
            $this->cms('sitemap') => Http::response(['generatedAt' => '2026-10-03T09:00:00.000Z', 'items' => [['type' => 'page', 'slug' => 'about', 'lastModified' => '2026-10-01T00:00:00.000Z'], ['type' => 'post', 'slug' => 'hello-world', 'lastModified' => '2026-10-02T00:00:00.000Z'], ['type' => 'event', 'slug' => 'friday-band', 'lastModified' => '2026-10-02T00:00:00.000Z']]]),
            $this->url('public/site') => Http::response($this->siteBody()),
        ];
    }

    public function test_home_renders_from_the_cms_api_with_the_service_token(): void
    {
        $this->useHttpCms();
        Http::fake($this->apiFakes());

        $this->get('/')->assertOk()->assertSee('Swim <i>slow</i>', false)->assertSee('Under the palms')->assertSee('The deep end')->assertSee('Lights on till late')->assertSee('https://cdn.test/hero-480.webp 480w', false)
            ->assertSee('<title>Default title</title>', false)->assertSee('Footer words')->assertSee('Live loud, swim <i>slow.</i>', false);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'public/cms/home') && $r->hasHeader('Authorization', 'Bearer svc-test-token'));
    }

    public function test_cms_responses_are_cached_per_url(): void
    {
        $this->useHttpCms();
        Http::fake($this->apiFakes());

        $this->get('/')->assertOk();
        $this->get('/')->assertOk();

        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), 'public/cms/site'))->all());
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), 'public/cms/home'))->all());
    }

    public function test_stale_content_is_served_when_the_cms_goes_down(): void
    {
        $this->useHttpCms(ttl: 0); // every read is "stale" so the client tries to refresh
        Http::fake($this->apiFakes());
        $this->get('/')->assertOk()->assertSee('Swim <i>slow</i>', false);

        Http::swap(new Factory);
        Http::fake([$this->cms('*') => fn () => throw new ConnectionException('down'), $this->url('public/site') => Http::response($this->siteBody())]);
        Cache::forget('cms:v1:breaker');
        $this->get('/')->assertOk()->assertSee('Swim <i>slow</i>', false)->assertSee('Lights on till late'); // stale-if-error
        $this->get('/')->assertOk()->assertSee('Swim <i>slow</i>', false); // circuit breaker: no waiting on the CMS again
    }

    public function test_cms_never_reachable_degrades_gracefully_and_booking_still_works(): void
    {
        $this->useHttpCms();
        Http::fake([$this->cms('*') => Http::response(['code' => 'boom'], 500), $this->url('public/site') => Http::response($this->siteBody()), $this->url('catalog/products*') => $this->page([]), $this->url('memberships/plans*') => $this->page([])]);

        $this->get('/')->assertOk()->assertSee('data-hero', false)->assertSee('Book a court'); // built-in fallback slide, no fake content
        $this->get('/about')->assertStatus(503)->assertHeader('Retry-After', '30')->assertSee('warming up')->assertSee('Pool tickets');
        $this->get('/pool')->assertOk(); // booking flows do not depend on CMS copy
        $this->get('/login')->assertOk()->assertSee('Sign');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/facilities/beauty-spa'); // static URLs still there
    }

    public function test_generic_page_and_sitemap_map_cms_types_to_site_urls(): void
    {
        $this->useHttpCms();
        Http::fake($this->apiFakes());

        $this->get('/about')->assertOk()->assertSee('We are here.')->assertSee('<title>About us | 007</title>', false);
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/blog/hello-world</loc>', $xml);
        $this->assertStringContainsString('/events/friday-band</loc>', $xml);
        $this->assertStringContainsString('<lastmod>2026-10-02</lastmod>', $xml);
        $this->get('/pages/unknown-page')->assertNotFound();
    }

    public function test_client_maps_query_params_and_detail_404_to_null(): void
    {
        $api = ['base_url' => 'https://api.r007.test', 'prefix' => '/api/v1', 'service_token' => 'svc-test-token', 'client_id' => 'web'];
        Http::fake([$this->cms('events/none') => Http::response(['code' => 'not_found'], 404), $this->cms('events*') => Http::response(['items' => [['slug' => 'a']], 'nextCursor' => null])]);
        $c = new HttpCmsClient($api);

        $this->assertSame('a', $c->events(['upcoming' => true, 'category' => 'MUSIC', 'limit' => 5])['items'][0]['slug']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'upcoming=true') && str_contains($r->url(), 'category=MUSIC') && str_contains($r->url(), 'limit=5'));
        $this->assertNull($c->event('none'));
    }

    public function test_cached_client_keeps_a_stale_copy_and_opens_a_circuit(): void
    {
        $calls = 0;
        $inner = new class(fn () => $calls++) extends FixtureCmsClient
        {
            public bool $down = false;

            public function __construct(public $tick) {}

            public function site(): array
            {
                ($this->tick)();
                if ($this->down) {
                    throw new CmsUnavailableException('down');
                }

                return ['brand' => ['name' => 'X']];
            }
        };
        $cache = new CachedCmsClient($inner, Cache::store('array'), 0, 3600);
        $this->assertSame('X', $cache->site()['brand']['name']);
        $inner->down = true;
        $this->assertSame('X', $cache->site()['brand']['name']); // stale-if-error
        $before = $calls;
        $cache->site();
        $this->assertSame($before, $calls, 'circuit open: the CMS is not asked again');
    }
}

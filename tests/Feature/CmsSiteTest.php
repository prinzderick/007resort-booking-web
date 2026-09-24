<?php

namespace Tests\Feature;

use App\Services\Cms\CmsClient;
use App\Services\Cms\FixtureCmsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ApiTestCase;

/** Every public page renders from the (fixture) CMS; SEO tags, sitemap and filters. */
class CmsSiteTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeApi(['public/site' => Http::response($this->siteBody()), 'bookings/resources*' => $this->page([$this->court()]), 'catalog/products*' => $this->page([]), 'memberships/plans*' => $this->page([])]);
    }

    /** @return array<string, array{string}> */
    public static function publicRoutes(): array
    {
        return array_map(fn ($u) => [$u], [
            '/', '/sports', '/spa', '/dining', '/pool', '/memberships', '/events', '/events/sunset-doubles', '/blog',
            '/blog/how-to-book-a-court-in-two-minutes', '/gallery', '/about', '/contact', '/faq', '/terms', '/privacy', '/cookies',
            '/facilities/sports-arena', '/facilities/restaurant', '/book/sports-arena', '/login', '/register',
        ]);
    }

    #[DataProvider('publicRoutes')]
    public function test_every_public_route_renders_with_fixtures(string $url): void
    {
        $this->get($url)->assertOk()
            ->assertSee('<title>', false)->assertSee('rel="canonical"', false)->assertSee('property="og:title"', false)
            ->assertSee('application/ld+json', false)->assertSee('id="main"', false)->assertDontSee('lorem', false);
    }

    public function test_home_is_driven_by_cms_sections(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-hero', false)->assertSee('Your weekend starts')->assertSee('<i>here.</i>', false) // accent word
            ->assertSee('data-bookbar', false)->assertSee('Courts that stay lit')->assertSee('data-count="16"', false) // stat count-up
            ->assertSee('Zero fuss.')->assertSee('Good')->assertSee('marquee', false)->assertSee('data-rail', false)
            ->assertSee('Sunset doubles every Saturday');
        $this->get('/')->assertSee('rel="preload" as="image"', false); // LCP preload
    }

    public function test_pages_have_their_own_seo_tags_and_json_ld(): void
    {
        $this->get('/events/sunset-doubles')->assertSee('"@type":"Event"', false)->assertSee('BreadcrumbList', false)->assertSee('og:title" content="Sunset doubles"', false);
        $this->get('/blog/how-to-book-a-court-in-two-minutes')->assertSee('"@type":"BlogPosting"', false)->assertSee('og:type" content="article"', false);
        $this->get('/')->assertSee('SportsActivityLocation', false)->assertSee('"@type":"WebSite"', false)->assertSee('twitter:card', false);
        $this->get('/facilities/sports-arena')->assertSee('LocalBusiness', false);
        $this->get('/about')->assertSee('<title>Built for the kind of day that runs to midnight. | 007 Resort &amp; Spa</title>', false)->assertDontSee('&amp;amp;', false);
    }

    public function test_html_entities_in_titles_are_escaped_once(): void
    {
        $this->get('/events')->assertSee('<title>What&#039;s on | 007 Resort &amp; Spa</title>', false)->assertDontSee('&amp;#039;', false);
    }

    public function test_sitemap_lists_static_and_cms_urls_and_robots_points_to_it(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();
        foreach (['/facilities/beauty-spa', '/events/sunset-doubles', '/blog/how-to-book-a-court-in-two-minutes', '/about', '/sports', '/pool', '/memberships'] as $path) {
            $this->assertStringContainsString($path.'</loc>', $xml);
        }
        $this->assertStringNotContainsString('/account', $xml);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /checkout')->assertSee('Disallow: /newsletter')->assertSee('sitemap.xml');
    }

    public function test_events_filter_by_month_and_category_and_export_ics(): void
    {
        $all = $this->get('/events')->assertOk()->assertSee('Sunset doubles')->assertSee('Five-a-side cup');
        preg_match_all('/events\?month=(\d{4}-\d{2})/', $all->getContent(), $m);
        $this->assertNotEmpty($m[1], 'month chips are rendered');
        $this->get('/events?category=MUSIC')->assertOk()->assertSee('Poolside DJ')->assertDontSee('Five-a-side cup');
        $this->get('/events?category=NOPE')->assertOk()->assertSee('Poolside DJ'); // unknown filter is ignored, not an error
        $this->get('/events/nothing-here')->assertNotFound();

        $ics = $this->get('/events/sunset-doubles.ics')->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $ics->assertSee('BEGIN:VEVENT', false)->assertSee('SUMMARY:Sunset doubles', false)->assertSee('DTSTART:', false)->assertSee('END:VCALENDAR', false);
        $this->get('/events/sunset-doubles')->assertSee('Add to calendar')->assertSee('/book/sports-arena');
    }

    public function test_blog_filters_search_paginates_and_404s(): void
    {
        $this->get('/blog')->assertOk()->assertSee('Featured')->assertSee('Play')->assertSee('Wellness');
        $this->get('/blog?category=wellness')->assertOk()->assertSee('A slow Sunday at the pool')->assertDontSee('Tennis for beginners');
        $this->get('/blog?q=suya')->assertOk()->assertSee('Inside the grill deck');
        $this->get('/blog?q=zzzzqqq')->assertOk()->assertSee('No stories found');
        $this->get('/blog?page=99')->assertOk();
        $this->get('/blog/not-a-post')->assertNotFound();
        $this->get('/blog/how-to-book-a-court-in-two-minutes')->assertSee('data-reading-bar', false)->assertSee('Keep')->assertSee('data-copy-link', false)->assertSee('data-subscribe', false);
    }

    public function test_gallery_has_album_chips_and_deep_linkable_photos(): void
    {
        $this->get('/gallery')->assertOk()->assertSee('data-album="pool"', false)->assertSee('data-photo="pool-1"', false)->assertSee('data-lightbox', false);
        $this->get('/gallery?album=spa')->assertOk()->assertSee('aria-current="true"', false);
    }

    public function test_quick_booking_bar_deep_links_into_the_real_flow(): void
    {
        $day = now('Africa/Lagos')->addDays(2)->format('Y-m-d');
        $this->get("/book-now?what=sports-arena&date={$day}&players=4")->assertRedirect(route('book.resources', ['slug' => 'sports-arena', 'date' => $day, 'players' => 4]));
        $this->get('/book-now?what=pool&players=3')->assertRedirect(route('pool', ['players' => 3]));
        $this->get('/book-now?what=restaurant')->assertRedirect(route('facility', 'restaurant'));
        $this->get('/book-now?what=moon-base')->assertNotFound();
        $this->get('/book-now?what=sports-arena&date=not-a-date')->assertRedirect(route('book.resources', ['slug' => 'sports-arena']));
    }

    public function test_themed_pages_show_live_courts_from_the_booking_api(): void
    {
        $this->get('/sports')->assertOk()->assertSee('Tennis Court 1')->assertSee('₦5,000')->assertSee('See times');
    }

    public function test_page_cache_headers_only_for_signed_out_visitors(): void
    {
        $this->get('/')->assertHeader('Cache-Control', 'max-age=60, private, stale-while-revalidate=300');
        $this->signIn()->get('/')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_csp_uses_a_nonce_and_allows_cms_media_origin(): void
    {
        config(['app.debug' => false, 'cms.media_hosts' => ['https://cdn.example.test']]);
        $res = $this->get('/')->assertOk();
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/", $csp);
        $this->assertStringContainsString('https://cdn.example.test', $csp);
        $this->assertStringContainsString('https://api.r007.test', $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        preg_match('/nonce-([A-Za-z0-9+\\/=]+)/', $csp, $m);
        $res->assertSee('nonce="'.$m[1].'"', false);
    }

    public function test_unknown_cms_page_is_a_friendly_404(): void
    {
        $this->get('/pages/does-not-exist')->assertNotFound()->assertSee('Out of bounds');
    }

    public function test_fixture_media_carries_responsive_variants_for_srcset(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('/srcset="[^"]*480w[^"]*960w[^"]*1600w/', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
    }

    public function test_fixture_client_is_the_default_test_driver(): void
    {
        $this->assertInstanceOf(FixtureCmsClient::class, app(CmsClient::class));
        $this->assertSame([], array_diff(['HERO_SLIDE', 'HIGHLIGHT', 'STAT', 'TESTIMONIAL', 'FAQ', 'PARTNER', 'CTA_BAND'], array_keys(app(CmsClient::class)->home()['byType'])));
        Cache::flush();
        $this->assertNotEmpty(app(CmsClient::class)->events(['upcoming' => true])['items']);
    }

    public function test_announcement_bar_and_open_now_pill_come_from_cms_settings(): void
    {
        $this->get('/')->assertSee('data-ann', false)->assertSee('data-ann-close', false)->assertSee('open-pill', false);
    }

    public function test_connection_exception_class_is_available(): void
    {
        $this->assertTrue(class_exists(ConnectionException::class));
    }
}

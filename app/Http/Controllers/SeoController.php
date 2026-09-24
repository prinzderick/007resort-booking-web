<?php

namespace App\Http\Controllers;

use App\Services\Cms\CmsClient;
use App\Services\Cms\CmsUnavailableException;
use Illuminate\Http\Response;

/** robots.txt and a sitemap.xml driven by the CMS sitemap endpoint (type + slug mapped to this site's URLs). */
class SeoController extends Controller
{
    public function __construct(private readonly CmsClient $cms) {}

    public function robots(): Response
    {
        $body = "User-agent: *\nDisallow: /account\nDisallow: /checkout\nDisallow: /payment\nDisallow: /tickets\nDisallow: /orders\nDisallow: /mock\nDisallow: /newsletter\nDisallow: /book-now\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function sitemap(): Response
    {
        $urls = [];
        $add = function (string $loc, ?string $lastmod = null, string $priority = '0.6') use (&$urls) {
            $urls[$loc] = ['loc' => $loc, 'lastmod' => $lastmod ? substr($lastmod, 0, 10) : null, 'priority' => $priority];
        };
        foreach (['home' => '1.0', 'pool' => '0.9', 'memberships.index' => '0.8', 'events.index' => '0.8', 'blog.index' => '0.7', 'gallery' => '0.6', 'about' => '0.6', 'contact' => '0.6', 'faq' => '0.5'] as $route => $prio) {
            $add(route($route), null, $prio);
        }
        foreach (['sports', 'spa', 'dining'] as $slug) {
            $add(url('/'.$slug), null, '0.9');
        }
        foreach (array_keys((array) config('site.facilities')) as $slug) {
            $add(route('facility', $slug), null, '0.7');
        }

        try {
            foreach ($this->cms->sitemap() as $i) {
                $slug = (string) ($i['slug'] ?? '');
                $loc = match ($i['type'] ?? '') {
                    'post' => route('blog.show', $slug),
                    'event' => route('events.show', $slug),
                    'album' => route('gallery').'#album-'.$slug,
                    'page' => match (true) {
                        $slug === 'home' => route('home'),
                        in_array($slug, ['sports', 'spa', 'dining', 'about', 'faq', 'contact', 'gallery', 'terms', 'privacy', 'cookies'], true) => url('/'.$slug),
                        $slug === 'events' => route('events.index'),
                        $slug === 'blog' => route('blog.index'),
                        $slug === 'pool' => route('pool'),
                        $slug === 'membership' => route('memberships.index'),
                        default => route('pages.show', $slug),
                    },
                    default => null,
                };
                if ($loc && ! str_contains($loc, '#')) {
                    $add($loc, $i['lastModified'] ?? null, ($i['type'] ?? '') === 'page' ? '0.6' : '0.5');
                }
            }
        } catch (CmsUnavailableException) {
            // CMS-driven URLs are simply omitted; the static ones above still make a valid sitemap.
        }

        return response()->view('sitemap', ['urls' => array_values($urls)], 200, ['Content-Type' => 'application/xml', 'Cache-Control' => 'public, max-age=900']);
    }
}

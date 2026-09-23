<?php

namespace App\Http\Controllers;

use App\Services\Online\SiteService;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function __construct(private readonly SiteService $site) {}

    public function home()
    {
        return view('home', ['site' => $this->site->site()]);
    }

    public function facility(string $slug)
    {
        $site = $this->site->site();
        abort_unless(isset($site['facilities'][$slug]), 404);

        return view('facility', ['site' => $site, 'facility' => $site['facilities'][$slug]]);
    }

    public function contact()
    {
        return view('contact', ['site' => $this->site->site()]);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nDisallow: /account\nDisallow: /checkout\nDisallow: /payment\nDisallow: /tickets\nDisallow: /mock\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        $urls = [route('home'), route('contact'), route('memberships.index')];
        foreach (array_keys((array) config('site.facilities')) as $slug) {
            $urls[] = route('facility', $slug);
        }

        return response()->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Cms\CmsUnavailableException;
use App\Services\Online\ContentService;

/** Generic CMS pages: /about, /faq, legal pages and /pages/{slug}. */
class ContentController extends Controller
{
    public function __construct(private readonly ContentService $content) {}

    public function about()
    {
        return $this->render('about');
    }

    public function faq()
    {
        return $this->render('faq', faqs: $this->content->faqs());
    }

    public function legal(string $slug)
    {
        abort_unless(in_array($slug, ['terms', 'privacy', 'cookies'], true), 404);

        return $this->render($slug);
    }

    public function show(string $slug)
    {
        return $this->render($slug, strict: true);
    }

    /** @param list<array<string, mixed>>|null $faqs */
    private function render(string $slug, bool $strict = false, ?array $faqs = null)
    {
        $page = $this->content->page($slug);
        abort_if(($strict || in_array($slug, ['terms', 'privacy', 'cookies'], true)) && ! $page['found'], 404);
        if ($this->content->unavailable() && ! $page['found']) {
            throw new CmsUnavailableException('page unavailable');
        }

        return view('pages.generic', [
            'slug' => $slug,
            'page' => $page,
            'faqs' => $faqs,
            'bands' => $this->content->home()['byType']['CTA_BAND'],
            'strip' => in_array($slug, ['about'], true) ? $this->content->strip('grounds', 8) : [],
            'legal' => in_array($slug, ['terms', 'privacy', 'cookies'], true),
        ]);
    }
}

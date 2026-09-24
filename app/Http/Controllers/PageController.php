<?php

namespace App\Http\Controllers;

use App\Services\Online\BookingService;
use App\Services\Online\ContentService;
use App\Services\Online\SiteContext;
use App\Services\Online\SiteService;
use App\Services\R007Api\R007ApiException;
use App\Support\Lagos;
use Illuminate\Http\Request;

/** Home, facility pages, themed pages (sports/spa/dining) and the "quick booking bar" hand-off. */
class PageController extends Controller
{
    public function __construct(
        private readonly SiteService $site,
        private readonly SiteContext $ctx,
        private readonly ContentService $content,
        private readonly BookingService $bookings,
    ) {}

    public function home()
    {
        $home = $this->content->home();
        $by = $home['byType'];
        $site = $this->site->site();
        $order = array_flip(['sports-arena', 'pool', 'beauty-spa', 'salon']);
        $bookable = collect($site['facilities'])->filter(fn ($f) => $f['flow'] !== 'info' && $f['online_available'])->sortBy(fn ($f, $slug) => $order[$slug] ?? 99)->all();

        // one highlight per category first (variety in the mosaic), then the rest
        $highlights = $by['HIGHLIGHT'];
        $mosaic = [];
        $seen = [];
        foreach ($highlights as $h) {
            $c = strtolower((string) ($h['category'] ?? ''));
            if (! isset($seen[$c]) && count($mosaic) < 4) {
                $mosaic[] = $h;
                $seen[$c] = true;
            }
        }
        foreach ($highlights as $h) {
            if (count($mosaic) < 4 && ! in_array($h, $mosaic, true)) {
                $mosaic[] = $h;
            }
        }
        $carousel = array_values(array_filter($highlights, fn ($h) => ! in_array($h, $mosaic, true)) ?: $highlights);

        $cta = collect($by['CTA_BAND']);
        $membership = $cta->first(fn ($c) => str_contains(strtolower((string) ($c['ctaLink'] ?? '')), 'member'));
        $marquee = collect($highlights)->pluck('title')->merge(collect($by['PARTNER'])->pluck('name'))->filter()->unique()->values()->all();

        return view('home', [
            'site' => $site,
            'by' => $by,
            'intro' => $this->content->page('home'),
            'mosaic' => $mosaic,
            'carousel' => $carousel,
            'membership' => $membership,
            'ctaBands' => $cta->reject(fn ($c) => $c === $membership)->values()->all(),
            'marquee' => $marquee,
            'events' => $this->content->events(['limit' => 4]),
            'posts' => $this->content->posts(['limit' => 4]),
            'strip' => $this->content->strip('grounds', 8),
            'faqs' => $this->content->faqs(null, 5),
            'bookable' => $bookable,
            'content' => $this->content,
            'facilitiesSvc' => $this->site,
        ]);
    }

    /** No-JS friendly target of the floating booking bar: /book-now?what=sports-arena&date=Y-m-d&players=2 */
    public function bookNow(Request $request)
    {
        $slug = (string) $request->query('what', 'sports-arena');
        $f = $this->site->facility($slug);
        abort_unless($f !== null, 404);
        $q = array_filter([
            'date' => Lagos::dayStart((string) $request->query('date')) ? (string) $request->query('date') : null,
            'players' => max(1, min(50, (int) $request->query('players', 1))) > 1 ? max(1, min(50, (int) $request->query('players'))) : null,
        ]);
        if ($f['flow'] === 'tickets') {
            return redirect()->route('pool', $q);
        }
        if ($f['flow'] === 'slots') {
            return redirect()->route('book.resources', ['slug' => $slug] + $q);
        }

        return redirect()->route('facility', $slug);
    }

    public function facility(string $slug)
    {
        $site = $this->site->site();
        abort_unless(isset($site['facilities'][$slug]), 404);
        $facility = $site['facilities'][$slug];
        $map = ['sports-arena' => 'sports', 'beauty-spa' => 'spa', 'salon' => 'spa', 'restaurant' => 'dining', 'bush-bar' => 'dining', 'cafe' => 'dining'];
        $own = $this->content->page($slug);
        $cms = $own['found'] ? $own : $this->content->page($map[$slug] ?? $slug);

        return view('facility', [
            'site' => $site,
            'facility' => $facility,
            'cms' => $cms,
            'strip' => $this->content->strip(config('site.facility_albums.'.$slug), 6),
            'others' => collect($site['facilities'])->except($slug)->take(4)->all(),
        ]);
    }

    /** Themed CMS pages that sit in front of a booking flow: /sports, /spa, /dining. */
    public function themed(string $slug)
    {
        abort_unless(in_array($slug, ['sports', 'spa', 'dining'], true), 404);
        $cfg = (array) config("site.page_defaults.$slug");
        $page = $this->content->page($slug);
        $live = ['sports' => 'sports-arena', 'spa' => 'beauty-spa'][$slug] ?? null;
        $resources = [];
        $notice = null;
        if ($live) {
            $facility = $this->site->facility($live);
            if (empty($facility['id']) || ! $facility['online_available']) {
                $notice = $facility['notice'] ?? 'Live availability is temporarily unavailable. Please try again shortly or call reception.';
            } else {
                try {
                    $resources = $this->bookings->resources($facility['ids'] ?? [$facility['id']]);
                } catch (R007ApiException $e) {
                    if (! $e->isUnavailable()) {
                        throw $e;
                    }
                    $notice = 'Live availability is temporarily unavailable. Please try again shortly or call reception.';
                }
            }
        }

        return view('pages.themed', [
            'slug' => $slug,
            'page' => $page,
            'resources' => $resources,
            'liveSlug' => $live,
            'notice' => $notice,
            'highlights' => $this->content->highlights($cfg['highlights'] ?? null),
            'faqs' => $this->content->faqs($cfg['faq'] ?? null, 4),
            'events' => ($cfg['events'] ?? null) ? $this->content->events(['category' => $cfg['events'], 'limit' => 3]) : [],
            'strip' => $this->content->strip($cfg['album'] ?? null, 15),
            'bands' => $this->content->home()['byType']['CTA_BAND'],
            'facilitiesSvc' => $this->site,
            'content' => $this->content,
        ]);
    }
}

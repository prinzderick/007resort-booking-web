<?php

namespace App\Services\Online;

use App\Services\Cms\CmsUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Assembles CMS content for the public pages, degrading to empty data (never an exception) when a piece is missing
 * or the CMS is unreachable. `unavailable()` is true when nothing could be loaded for this request.
 */
class ContentService
{
    private bool $unavailable = false;

    public function __construct(private readonly SiteContext $ctx) {}

    public function unavailable(): bool
    {
        return $this->unavailable || ! $this->ctx->up();
    }

    /** @template T @param callable():T $fn @param T $default @return T */
    private function safe(callable $fn, mixed $default): mixed
    {
        try {
            return $fn();
        } catch (CmsUnavailableException) {
            $this->unavailable = true;

            return $default;
        }
    }

    /** @return array{sections: list<array<string, mixed>>, byType: array<string, list<array<string, mixed>>>} */
    public function home(): array
    {
        $h = $this->safe(fn () => $this->ctx->cms()->home(), ['sections' => [], 'byType' => []]);
        $by = (array) ($h['byType'] ?? []);
        if ($by === [] && ! empty($h['sections'])) {
            foreach ($h['sections'] as $s) {
                $by[$s['type']][] = $s;
            }
        }

        return ['sections' => (array) ($h['sections'] ?? []), 'byType' => $by + array_fill_keys(['HERO_SLIDE', 'HIGHLIGHT', 'STAT', 'TESTIMONIAL', 'FAQ', 'PARTNER', 'CTA_BAND'], [])];
    }

    /**
     * A CMS page with defaults from config('site.page_defaults') when editors have not published it. `found` is
     * false for defaults (so generic /pages/{slug} can 404 while /sports still renders).
     *
     * @return array<string, mixed>
     */
    public function page(string $slug): array
    {
        $p = $this->safe(fn () => $this->ctx->cms()->page($slug), null);
        $d = (array) config("site.page_defaults.$slug", []);
        $hero = $p['hero'] ?? null;

        return ($p ?? []) + [
            'found' => $p !== null,
            'slug' => $slug,
            'title' => $d['title'] ?? Str::headline($slug),
            'subtitle' => $d['subtitle'] ?? null,
            'hero' => null,
            'bodyHtml' => '',
            'seo' => [],
        ] + ['heroFallback' => $hero ?: $this->fallbackHero()];
    }

    /** First home hero slide image, used when a page has no hero of its own. @return array<string, mixed>|null */
    public function fallbackHero(): ?array
    {
        $slides = $this->home()['byType']['HERO_SLIDE'] ?? [];

        return $slides[0]['media'] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function highlights(?string $category = null, ?int $limit = null): array
    {
        $items = $this->home()['byType']['HIGHLIGHT'] ?? [];
        if ($category) {
            $items = array_values(array_filter($items, fn ($h) => strtolower((string) ($h['category'] ?? '')) === strtolower($category)));
        }

        return $limit ? array_slice($items, 0, $limit) : $items;
    }

    /** @return list<array<string, mixed>> */
    public function faqs(?string $topic = null, ?int $limit = null): array
    {
        $items = $this->home()['byType']['FAQ'] ?? [];
        if ($topic) {
            $topical = array_values(array_filter($items, fn ($f) => strcasecmp((string) ($f['topic'] ?? ''), $topic) === 0));
            $items = $topical ?: $items;
        }

        return $limit ? array_slice($items, 0, $limit) : $items;
    }

    /** @return list<array<string, mixed>> */
    public function events(array $query = []): array
    {
        return $this->safe(fn () => $this->ctx->cms()->events($query + ['upcoming' => true])['items'], []);
    }

    /** @return list<array<string, mixed>> */
    public function posts(array $query = []): array
    {
        return $this->safe(fn () => $this->ctx->cms()->posts($query)['items'], []);
    }

    /** All published posts (follows cursors, bounded) so the blog can paginate/filter locally. @return list<array<string, mixed>> */
    public function allPosts(array $query = []): array
    {
        return $this->safe(function () use ($query) {
            $items = [];
            $cursor = null;
            for ($i = 0; $i < 5; $i++) {
                $r = $this->ctx->cms()->posts($query + ['limit' => 50] + ($cursor ? ['cursor' => $cursor] : []));
                array_push($items, ...$r['items']);
                $cursor = $r['nextCursor'] ?? null;
                if (! $cursor) {
                    break;
                }
            }

            return $items;
        }, []);
    }

    /** @return list<array<string, mixed>> */
    public function albums(): array
    {
        return $this->safe(fn () => $this->ctx->cms()->albums(), []);
    }

    /** @return array<string, mixed>|null */
    public function album(?string $slug): ?array
    {
        return $slug ? $this->safe(fn () => $this->ctx->cms()->album($slug), null) : null;
    }

    /** Photos for a themed strip: the named album, else the first album that has photos. @return list<array<string, mixed>> */
    public function strip(?string $albumSlug, int $limit = 8): array
    {
        $a = $this->album($albumSlug);
        if (! $a) {
            foreach ($this->albums() as $cand) {
                if (($cand['itemCount'] ?? 0) > 0) {
                    $a = $this->album($cand['slug']);
                    break;
                }
            }
        }

        return array_slice((array) ($a['items'] ?? []), 0, $limit);
    }

    /**
     * Pick the album photo that best illustrates a bookable resource by name ("Lawn Tennis Court 1" -> a tennis photo),
     * matching words against alt text / tags; falls back to cycling through the album. @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>|null
     */
    public static function photoFor(array $items, string $name, int $index): ?array
    {
        if ($items === []) {
            return null;
        }
        $words = array_filter(preg_split('/[^a-z]+/', strtolower($name)) ?: [], fn ($w) => strlen($w) >= 4 && ! in_array($w, ['court', 'lawn', 'male', 'female', 'room', 'pitch'], true));
        $hits = [];
        foreach ($items as $it) {
            $hay = strtolower(($it['alt'] ?? '').' '.implode(' ', (array) ($it['tags'] ?? [])).' '.($it['media']['alt'] ?? '').' '.implode(' ', (array) ($it['media']['tags'] ?? [])));
            foreach ($words as $w) {
                if (str_contains($hay, $w)) {
                    $hits[] = $it;
                    break;
                }
            }
        }
        $pool = $hits ?: $items;

        return $pool[$index % count($pool)]['media'] ?? null;
    }

    /**
     * Where an event's "book" button goes: an on-site path, an external ticket URL, or the facility booking flow.
     *
     * @return array{href: string, label: string, external: bool}
     */
    public function eventLink(array $event, SiteService $facilities): array
    {
        $t = (array) ($event['ticket'] ?? []);
        $label = $this->ctx->site()['booking']['eventsCtaLabel'] ?? 'Get tickets';
        if (! empty($t['url'])) {
            $external = preg_match('#^https?://#i', $t['url']) === 1;

            return ['href' => $t['url'], 'label' => $label, 'external' => $external];
        }
        $fid = $t['facilityId'] ?? ($event['venue']['facilityId'] ?? null);
        if ($fid && ($slug = $facilities->slugForFacilityId($fid))) {
            $flow = $facilities->facility($slug)['flow'] ?? 'info';

            return ['href' => $flow === 'tickets' ? route('pool') : ($flow === 'slots' ? route('book.resources', $slug) : route('facility', $slug)), 'label' => $label, 'external' => false];
        }

        return ['href' => route('contact'), 'label' => 'Ask about this', 'external' => false];
    }

    /** Events grouped by Lagos month (Y-m) => label. @param list<array<string, mixed>> $events @return array<string, string> */
    public static function months(array $events): array
    {
        $out = [];
        foreach ($events as $e) {
            $d = CarbonImmutable::parse($e['startsAt'])->setTimezone(config('r007.display_timezone', 'Africa/Lagos'));
            $out[$d->format('Y-m')] = $d->format('F Y');
        }
        ksort($out);

        return $out;
    }
}

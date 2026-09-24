<?php

namespace App\Services\Cms;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Offline CMS: bundled sample content from resources/cms-fixtures, in exactly the shapes of the real contract.
 * Selected with CMS_FIXTURES=true or R007_MOCK=true. Subscription state lives in the cache store (no DB, no mailer).
 */
class FixtureCmsClient implements CmsClient
{
    /** @var array<string, mixed> */
    private array $memo = [];

    private function load(string $name): mixed
    {
        return $this->memo[$name] ??= require resource_path("cms-fixtures/{$name}.php");
    }

    private function html(?string $md): string
    {
        return Str::markdown((string) $md, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function site(): array
    {
        return $this->load('site');
    }

    public function home(): array
    {
        $sections = $this->load('home');
        $by = array_fill_keys(['HERO_SLIDE', 'HIGHLIGHT', 'STAT', 'TESTIMONIAL', 'FAQ', 'PARTNER', 'CTA_BAND'], []);
        foreach ($sections as $s) {
            $by[$s['type']][] = $s;
        }

        return ['sections' => $sections, 'byType' => $by, 'updatedAt' => '2026-09-20T09:00:00.000Z'];
    }

    public function pages(): array
    {
        return array_values(array_map(fn ($p) => ['slug' => $p['slug'], 'title' => $p['title'], 'showInFooter' => $p['showInFooter'], 'updatedAt' => $p['updatedAt']], $this->load('pages')));
    }

    public function page(string $slug): ?array
    {
        $p = $this->load('pages')[$slug] ?? null;

        return $p ? $p + ['bodyHtml' => $this->html($p['bodyMarkdown'])] : null;
    }

    /** @return list<array<string, mixed>> occurrences of every event (weekly series expanded), soonest first */
    private function occurrences(int $perSeries): array
    {
        $out = [];
        foreach ($this->load('events') as $e) {
            $n = $e['isRecurring'] ? $perSeries : 1;
            for ($i = 0; $i < $n; $i++) {
                $shift = fn (string $iso) => CarbonImmutable::parse($iso)->addWeeks($i);
                $o = $e;
                $o['startsAt'] = $shift($e['startsAt'])->format('Y-m-d\TH:i:s.000\Z');
                $o['endsAt'] = $shift($e['endsAt'])->format('Y-m-d\TH:i:s.000\Z');
                $o['startsAtLocal'] = CarbonImmutable::parse($o['startsAt'])->setTimezone($e['timezone'])->format('c');
                $o['endsAtLocal'] = CarbonImmutable::parse($o['endsAt'])->setTimezone($e['timezone'])->format('c');
                $o['occurrenceKey'] = $e['slug'].'@'.CarbonImmutable::parse($o['startsAt'])->format('Y-m-d\TH:i:s\Z');
                unset($o['bodyMarkdown'], $o['seo']);
                $out[] = $o;
            }
        }

        return $out;
    }

    public function events(array $query = []): array
    {
        $now = CarbonImmutable::now();
        $upcoming = $query['upcoming'] ?? null;
        $all = $this->occurrences((int) ($query['occurrences'] ?? 4));
        $items = array_values(array_filter($all, function ($e) use ($upcoming, $query, $now) {
            $ended = CarbonImmutable::parse($e['endsAt'])->lt($now);
            if ($upcoming === true && $ended) {
                return false;
            }
            if ($upcoming === false && ! $ended) {
                return false;
            }
            if (! empty($query['category']) && $e['category'] !== strtoupper($query['category'])) {
                return false;
            }
            if (! empty($query['featured']) && ! $e['featured']) {
                return false;
            }

            return true;
        }));
        usort($items, fn ($a, $b) => $upcoming === false ? strcmp($b['startsAt'], $a['startsAt']) : strcmp($a['startsAt'], $b['startsAt']));

        return ['items' => array_slice($items, 0, (int) ($query['limit'] ?? 12)), 'nextCursor' => null];
    }

    public function event(string $slug): ?array
    {
        foreach ($this->load('events') as $e) {
            if ($e['slug'] === $slug) {
                $occ = array_values(array_filter($this->occurrences(8), fn ($o) => $o['slug'] === $slug && CarbonImmutable::parse($o['endsAt'])->gte(CarbonImmutable::now())));

                return $e + ['bodyHtml' => $this->html($e['bodyMarkdown']), 'nextOccurrences' => array_map(fn ($o) => array_intersect_key($o, array_flip(['startsAt', 'endsAt', 'startsAtLocal', 'endsAtLocal'])), $occ), 'occurrenceKey' => $slug];
            }
        }

        return null;
    }

    private function postItem(array $p): array
    {
        return array_diff_key($p, array_flip(['bodyMarkdown', 'seo', 'updatedAt']));
    }

    public function posts(array $query = []): array
    {
        $all = $this->load('posts');
        usort($all, fn ($a, $b) => strcmp($b['publishedAt'], $a['publishedAt']));
        $q = mb_strtolower(trim((string) ($query['q'] ?? '')));
        $items = array_values(array_filter($all, function ($p) use ($query, $q) {
            if (! empty($query['category']) && ($p['category']['slug'] ?? null) !== $query['category']) {
                return false;
            }
            if (! empty($query['tag']) && ! in_array($query['tag'], $p['tags'], true)) {
                return false;
            }
            if (! empty($query['featured']) && empty($p['featured'])) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($p['title'].' '.$p['excerpt'].' '.$p['bodyMarkdown']), $q);
        }));

        return ['items' => array_map(fn ($p) => $this->postItem($p), array_slice($items, 0, (int) ($query['limit'] ?? 12))), 'nextCursor' => null];
    }

    public function post(string $slug): ?array
    {
        foreach ($this->load('posts') as $p) {
            if ($p['slug'] === $slug) {
                $related = array_values(array_filter($this->load('posts'), fn ($o) => $o['slug'] !== $slug));
                usort($related, fn ($a, $b) => ($b['category']['slug'] === $p['category']['slug']) <=> ($a['category']['slug'] === $p['category']['slug']));

                return $this->postItem($p) + ['bodyMarkdown' => $p['bodyMarkdown'], 'bodyHtml' => $this->html($p['bodyMarkdown']), 'seo' => $p['seo'], 'updatedAt' => $p['updatedAt'], 'related' => array_map(fn ($o) => $this->postItem($o), array_slice($related, 0, 3))];
            }
        }

        return null;
    }

    public function postCategories(): array
    {
        $by = [];
        foreach ($this->load('posts') as $p) {
            $c = $p['category'];
            $by[$c['slug']] ??= $c + ['id' => 'cat-'.$c['slug'], 'description' => null, 'postCount' => 0];
            $by[$c['slug']]['postCount']++;
        }

        return array_values($by);
    }

    public function albums(): array
    {
        return array_map(fn ($a) => array_diff_key($a, ['items' => 1]), $this->load('gallery'));
    }

    public function album(string $slug): ?array
    {
        foreach ($this->load('gallery') as $a) {
            if ($a['slug'] === $slug) {
                return $a;
            }
        }

        return null;
    }

    public function sitemap(): array
    {
        $out = [];
        foreach ($this->load('pages') as $p) {
            $out[] = ['type' => 'page', 'slug' => $p['slug'], 'lastModified' => $p['updatedAt']];
        }
        foreach ($this->load('posts') as $p) {
            $out[] = ['type' => 'post', 'slug' => $p['slug'], 'lastModified' => $p['updatedAt']];
        }
        foreach ($this->load('events') as $e) {
            $out[] = ['type' => 'event', 'slug' => $e['slug'], 'lastModified' => $e['updatedAt']];
        }
        foreach ($this->load('gallery') as $a) {
            $out[] = ['type' => 'album', 'slug' => $a['slug'], 'lastModified' => '2026-09-01T09:00:00.000Z'];
        }

        return $out;
    }

    public function subscribe(string $email, ?string $name, string $source, bool $consent, string $consentText, string $honeypot, ?string $clientIp): array
    {
        if (! $consent) {
            throw new CmsRequestException(422, 'validation_failed', 'Please tick the consent box.', ['consent' => ['Please tick the consent box.']]);
        }
        if ($honeypot === '') {
            $email = strtolower($email);
            $subs = Cache::get('cms.fixture.subs', []);
            if (($subs[$email]['status'] ?? null) !== 'CONFIRMED') {
                $token = Str::random(24);
                $subs[$email] = ['status' => 'PENDING', 'token' => $token];
                Cache::put('cms.fixture.subs', $subs, 86400);
                Cache::put("cms.fixture.token.$token", $email, 86400);
                Cache::put('cms.fixture.last_token', $token, 3600); // fixture mode has no mailer: tests/demos read it here
            }
        }

        return ['status' => 'CHECK_EMAIL'];
    }

    private function emailFor(string $token): string
    {
        $email = Cache::get("cms.fixture.token.$token");
        if (! $email) {
            throw new CmsRequestException(404, 'invalid_token', 'This link is not valid.');
        }

        return $email;
    }

    public function previewConfirmation(string $token): array
    {
        $email = $this->emailFor($token);

        return ['valid' => true, 'status' => Cache::get('cms.fixture.subs', [])[$email]['status'] ?? 'PENDING'];
    }

    public function confirmSubscription(string $token): array
    {
        $email = $this->emailFor($token);
        $subs = Cache::get('cms.fixture.subs', []);
        $subs[$email]['status'] = 'CONFIRMED';
        Cache::put('cms.fixture.subs', $subs, 86400);

        return ['status' => 'CONFIRMED'];
    }

    public function unsubscribe(string $token): array
    {
        $email = $this->emailFor($token);
        $subs = Cache::get('cms.fixture.subs', []);
        $subs[$email]['status'] = 'UNSUBSCRIBED';
        Cache::put('cms.fixture.subs', $subs, 86400);

        return ['status' => 'UNSUBSCRIBED'];
    }

    public function contact(array $data, ?string $clientIp): array
    {
        Cache::put('cms.fixture.last_contact', $data, 600);

        return ['status' => 'RECEIVED'];
    }
}

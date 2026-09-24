<?php

namespace App\Services\Cms;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;

/**
 * Decorator: server-side cache of CMS reads. Fresh for `$ttl` seconds; a copy is kept for `$staleTtl` and served
 * when the CMS is slow/down (stale-if-error), so public pages keep rendering. After a failed refresh a short
 * circuit-breaker stops every request from waiting on the timeout again. Writes are never cached.
 */
class CachedCmsClient implements CmsClient
{
    private const BREAKER_SECONDS = 15;

    public function __construct(private readonly CmsClient $inner, private readonly Repository $cache, private readonly int $ttl = 60, private readonly int $staleTtl = 86400) {}

    /** @template T @param callable():T $fetch @return T */
    private function cached(string $key, callable $fetch): mixed
    {
        $key = 'cms:v1:'.$key;
        $entry = $this->cache->get($key);
        $now = time();
        if (is_array($entry) && $now - $entry['at'] < $this->ttl) {
            return $entry['data'];
        }
        if (is_array($entry) && $this->cache->has('cms:v1:breaker')) {
            return $entry['data']; // CMS recently failed: serve stale without waiting on it again
        }
        if (! is_array($entry) && $this->cache->has('cms:v1:breaker')) {
            throw new CmsUnavailableException('CMS unavailable (circuit open)');
        }

        try {
            $data = $fetch();
            $this->cache->put($key, ['at' => $now, 'data' => $data], $this->staleTtl);

            return $data;
        } catch (CmsUnavailableException $e) {
            $this->cache->put('cms:v1:breaker', 1, self::BREAKER_SECONDS);
            Log::warning('cms unavailable', ['stale' => is_array($entry)]);
            if (is_array($entry)) {
                return $entry['data'];
            }
            throw $e;
        }
    }

    private function k(string $name, mixed ...$args): string
    {
        return $name.':'.md5(json_encode($args));
    }

    public function site(): array
    {
        return $this->cached('site', fn () => $this->inner->site());
    }

    public function home(): array
    {
        return $this->cached('home', fn () => $this->inner->home());
    }

    public function pages(): array
    {
        return $this->cached('pages', fn () => $this->inner->pages());
    }

    public function page(string $slug): ?array
    {
        return $this->cached($this->k('page', $slug), fn () => $this->inner->page($slug));
    }

    public function posts(array $query = []): array
    {
        return $this->cached($this->k('posts', $query), fn () => $this->inner->posts($query));
    }

    public function post(string $slug): ?array
    {
        return $this->cached($this->k('post', $slug), fn () => $this->inner->post($slug));
    }

    public function postCategories(): array
    {
        return $this->cached('postcats', fn () => $this->inner->postCategories());
    }

    public function events(array $query = []): array
    {
        return $this->cached($this->k('events', $query), fn () => $this->inner->events($query));
    }

    public function event(string $slug): ?array
    {
        return $this->cached($this->k('event', $slug), fn () => $this->inner->event($slug));
    }

    public function albums(): array
    {
        return $this->cached('albums', fn () => $this->inner->albums());
    }

    public function album(string $slug): ?array
    {
        return $this->cached($this->k('album', $slug), fn () => $this->inner->album($slug));
    }

    public function sitemap(): array
    {
        return $this->cached('sitemap', fn () => $this->inner->sitemap());
    }

    public function subscribe(string $email, ?string $name, string $source, bool $consent, string $consentText, string $honeypot, ?string $clientIp): array
    {
        return $this->inner->subscribe($email, $name, $source, $consent, $consentText, $honeypot, $clientIp);
    }

    public function previewConfirmation(string $token): array
    {
        return $this->inner->previewConfirmation($token);
    }

    public function confirmSubscription(string $token): array
    {
        return $this->inner->confirmSubscription($token);
    }

    public function unsubscribe(string $token): array
    {
        return $this->inner->unsubscribe($token);
    }

    public function contact(array $data, ?string $clientIp): array
    {
        return $this->inner->contact($data, $clientIp);
    }
}

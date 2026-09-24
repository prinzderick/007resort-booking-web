<?php

namespace App\Services\Cms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Real CMS over HTTP: `{r007.api.base_url}/api/v1/public/cms/*` with the website service token. */
class HttpCmsClient implements CmsClient
{
    /** @param array<string, mixed> $api  the "r007.api" config array */
    public function __construct(private readonly array $api, private readonly string $path = 'public/cms', private readonly int $timeout = 4) {}

    private function request(?string $clientIp = null): PendingRequest
    {
        $base = rtrim((string) $this->api['base_url'], '/').'/'.trim((string) ($this->api['prefix'] ?? '/api/v1'), '/').'/'.trim($this->path, '/');
        $r = Http::baseUrl($base)
            ->acceptJson()
            ->withHeaders(['X-R007-Client' => (string) ($this->api['client_id'] ?? ''), 'X-Request-Id' => (string) Str::uuid()])
            ->timeout($this->timeout)
            ->connectTimeout(min(2, $this->timeout));
        if (! empty($this->api['service_token'])) {
            $r = $r->withToken((string) $this->api['service_token']);
        }
        if ($clientIp) {
            $r = $r->withHeaders(['X-Client-IP' => $clientIp]);
        }

        return $r;
    }

    /** @param array<string, mixed> $query @return array<string, mixed>|null null on 404 */
    private function read(string $path, array $query = [], bool $nullOn404 = false): ?array
    {
        $query = array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, array_filter($query, fn ($v) => $v !== null && $v !== ''));
        try {
            $res = $this->request()->get(ltrim($path, '/'), $query);
        } catch (ConnectionException $e) {
            throw new CmsUnavailableException('CMS unreachable', 0, $e);
        }
        if ($res->status() === 404 && $nullOn404) {
            return null;
        }
        if ($res->failed()) {
            // Privacy: log only status and the stable problem code.
            Log::warning('cms read failed', ['path' => $path, 'status' => $res->status(), 'code' => $res->json('code')]);
            throw new CmsUnavailableException("CMS answered {$res->status()}");
        }

        return (array) $res->json();
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    private function write(string $path, array $body, ?string $clientIp): array
    {
        try {
            $res = $this->request($clientIp)->withHeaders(['Idempotency-Key' => (string) Str::uuid()])->post(ltrim($path, '/'), $body);
        } catch (ConnectionException $e) {
            throw new CmsUnavailableException('CMS unreachable', 0, $e);
        }

        return $this->writeResult($res);
    }

    /** @return array<string, mixed> */
    private function writeResult(Response $res): array
    {
        if ($res->successful()) {
            return (array) $res->json();
        }
        if ($res->serverError()) {
            throw new CmsUnavailableException("CMS answered {$res->status()}");
        }
        $p = (array) $res->json();
        $errors = is_array($p['errors'] ?? null) ? $p['errors'] : [];
        throw new CmsRequestException($res->status(), (string) ($p['code'] ?? 'error'), (string) ($p['detail'] ?? $p['title'] ?? ''), $errors, ($ra = $res->header('Retry-After')) !== '' ? (int) $ra : null);
    }

    public function site(): array
    {
        return $this->read('site') ?? [];
    }

    public function home(): array
    {
        $h = $this->read('home') ?? [];
        $h['sections'] ??= [];
        $h['byType'] ??= [];

        return $h;
    }

    public function pages(): array
    {
        return (array) ($this->read('pages')['items'] ?? []);
    }

    public function page(string $slug): ?array
    {
        return $this->read('pages/'.rawurlencode($slug), [], true);
    }

    public function posts(array $query = []): array
    {
        $r = $this->read('posts', $query) ?? [];

        return ['items' => (array) ($r['items'] ?? []), 'nextCursor' => $r['nextCursor'] ?? null];
    }

    public function post(string $slug): ?array
    {
        return $this->read('posts/'.rawurlencode($slug), [], true);
    }

    public function postCategories(): array
    {
        return (array) ($this->read('post-categories')['items'] ?? []);
    }

    public function events(array $query = []): array
    {
        $r = $this->read('events', $query) ?? [];

        return ['items' => (array) ($r['items'] ?? []), 'nextCursor' => $r['nextCursor'] ?? null];
    }

    public function event(string $slug): ?array
    {
        return $this->read('events/'.rawurlencode($slug), [], true);
    }

    public function albums(): array
    {
        return (array) ($this->read('gallery/albums')['items'] ?? []);
    }

    public function album(string $slug): ?array
    {
        return $this->read('gallery/albums/'.rawurlencode($slug), [], true);
    }

    public function sitemap(): array
    {
        return (array) ($this->read('sitemap')['items'] ?? []);
    }

    public function subscribe(string $email, ?string $name, string $source, bool $consent, string $consentText, string $honeypot, ?string $clientIp): array
    {
        return $this->write('subscribers', array_filter([
            'email' => $email, 'name' => $name, 'source' => $source, 'consent' => $consent, 'consentText' => $consentText, 'website' => $honeypot,
        ], fn ($v) => $v !== null), $clientIp);
    }

    public function previewConfirmation(string $token): array
    {
        try {
            return (array) ($this->read('subscribers/confirm/'.rawurlencode($token)) ?? []);
        } catch (CmsUnavailableException $e) {
            throw $e;
        }
    }

    public function confirmSubscription(string $token): array
    {
        return $this->write('subscribers/confirm/'.rawurlencode($token), [], null);
    }

    public function unsubscribe(string $token): array
    {
        return $this->write('subscribers/unsubscribe/'.rawurlencode($token), [], null);
    }

    public function contact(array $data, ?string $clientIp): array
    {
        return $this->write('contact', array_filter($data, fn ($v) => $v !== null && $v !== ''), $clientIp);
    }
}

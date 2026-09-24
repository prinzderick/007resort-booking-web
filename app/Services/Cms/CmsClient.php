<?php

namespace App\Services\Cms;

/**
 * Read/write access to the CMS module of 007resort-api (`GET/POST /api/v1/public/cms/*`, contract: the API repo's
 * docs/CMS_API.md section 3). Methods map 1:1 to endpoints and return the decoded JSON (camelCase arrays):
 * media objects carry `variants` for srcset, Markdown fields come with `bodyHtml`.
 *
 * Implementations: FixtureCmsClient (offline dev/tests), HttpCmsClient (real API), CachedCmsClient (decorator:
 * short TTL + stale-if-error). Reads throw CmsUnavailableException when nothing (not even a stale copy) is available;
 * detail reads return null for an unknown/unpublished slug; writes throw CmsRequestException for 4xx problems.
 */
interface CmsClient
{
    /** @return array<string, mixed> brand, contact, hours, social, seo, announcement, booking, footer */
    public function site(): array;

    /** @return array{sections: list<array<string, mixed>>, byType: array<string, list<array<string, mixed>>>, updatedAt?: string} */
    public function home(): array;

    /** @return list<array<string, mixed>> */
    public function pages(): array;

    /** @return array<string, mixed>|null */
    public function page(string $slug): ?array;

    /**
     * @param  array{category?: string, tag?: string, q?: string, featured?: bool, limit?: int, cursor?: string}  $query
     * @return array{items: list<array<string, mixed>>, nextCursor: ?string}
     */
    public function posts(array $query = []): array;

    /** @return array<string, mixed>|null */
    public function post(string $slug): ?array;

    /** @return list<array<string, mixed>> */
    public function postCategories(): array;

    /**
     * @param  array{upcoming?: bool, category?: string, featured?: bool, occurrences?: int, limit?: int, cursor?: string}  $query
     * @return array{items: list<array<string, mixed>>, nextCursor: ?string}
     */
    public function events(array $query = []): array;

    /** @return array<string, mixed>|null */
    public function event(string $slug): ?array;

    /** @return list<array<string, mixed>> */
    public function albums(): array;

    /** @return array<string, mixed>|null album with `items` */
    public function album(string $slug): ?array;

    /** @return list<array{type: string, slug: string, lastModified?: string}> */
    public function sitemap(): array;

    /** @return array{status: string} always CHECK_EMAIL for a valid request */
    public function subscribe(string $email, ?string $name, string $source, bool $consent, string $consentText, string $honeypot, ?string $clientIp): array;

    /** @return array{valid: bool, status?: string} @throws CmsRequestException 404 invalid_token / 410 token_expired */
    public function previewConfirmation(string $token): array;

    /** @return array{status: string} @throws CmsRequestException */
    public function confirmSubscription(string $token): array;

    /** @return array{status: string} @throws CmsRequestException */
    public function unsubscribe(string $token): array;

    /** @param array{name: string, email: string, phone?: ?string, topic?: string, message: string, website?: string} $data @return array{status: string} @throws CmsRequestException */
    public function contact(array $data, ?string $clientIp): array;
}

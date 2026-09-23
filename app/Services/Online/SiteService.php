<?php

namespace App\Services\Online;

use App\Services\R007Api\R007ApiClient;
use App\Services\R007Api\R007ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Public site content: static copy from config/site.php overlaid with what the
 * API says (contact, hours, per-facility online availability). Rendering never
 * fails because the API is down: the static content is the fallback and the
 * `degraded` flag lets views show a calm notice.
 *
 * PROPOSED API: GET /public/site (see docs/API_DEPENDENCIES.md).
 */
class SiteService
{
    public function __construct(private readonly R007ApiClient $api) {}

    /**
     * @return array{contact: array<string, mixed>, hours: string, facilities: array<string, array<string, mixed>>, degraded: bool}
     */
    public function site(): array
    {
        $remote = $this->remote();

        $contact = array_filter((array) ($remote['data']['contact'] ?? []), fn ($v) => filled($v))
            + array_filter((array) config('site.contact'), fn ($v) => filled($v));
        if (isset($contact['mapUrl'])) {
            $contact['map_url'] = $contact['mapUrl'];
        }

        $facilities = [];
        foreach ((array) config('site.facilities') as $slug => $cfg) {
            $api = $this->matchApiFacility($cfg['match'] ?? [], (array) ($remote['data']['facilities'] ?? []));
            $facilities[$slug] = $cfg + [
                'slug' => $slug,
                'id' => $api['id'] ?? null,
                'hours' => $api['openingHours'] ?? null,
                'online_available' => $remote['ok'] ? (bool) ($api['onlineBookable'] ?? ($api !== null)) : false,
                'notice' => $api['onlineNotice'] ?? null,
                'api_description' => $api['description'] ?? null,
                'phone' => $api['phone'] ?? null,
            ];
            if (! empty($api['name']) && empty($cfg['name'])) {
                $facilities[$slug]['name'] = $api['name'];
            }
        }

        return [
            'contact' => $contact,
            'hours' => (string) ($remote['data']['openingHours'] ?? config('site.default_hours')),
            'facilities' => $facilities,
            'degraded' => ! $remote['ok'],
        ];
    }

    public function slugForFacilityId(string $facilityId): ?string
    {
        foreach ($this->site()['facilities'] as $slug => $f) {
            if (($f['id'] ?? null) === $facilityId) {
                return $slug;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function facility(string $slug): ?array
    {
        return $this->site()['facilities'][$slug] ?? null;
    }

    /**
     * @return array{ok: bool, data: array<string, mixed>}
     */
    private function remote(): array
    {
        $cached = Cache::get('r007.public_site');
        if (is_array($cached)) {
            return ['ok' => true, 'data' => $cached];
        }

        try {
            $data = $this->api->get('public/site');
            Cache::put('r007.public_site', $data, 60);

            return ['ok' => true, 'data' => $data];
        } catch (R007ApiException $e) {
            // Privacy: log only status and code, never payload/PII.
            Log::warning('public site data unavailable', ['status' => $e->status, 'code' => $e->code()]);

            return ['ok' => false, 'data' => []];
        }
    }

    /**
     * @param  list<string>  $match
     * @param  list<array<string, mixed>>  $apiFacilities
     * @return array<string, mixed>|null
     */
    private function matchApiFacility(array $match, array $apiFacilities): ?array
    {
        foreach ($apiFacilities as $f) {
            $haystack = [strtoupper((string) ($f['kind'] ?? '')), strtoupper((string) ($f['code'] ?? ''))];
            foreach ($match as $needle) {
                foreach ($haystack as $h) {
                    if ($h !== '' && ($h === $needle || str_starts_with($h, $needle.'_') || str_starts_with($h, $needle))) {
                        return $f;
                    }
                }
            }
        }

        return null;
    }
}

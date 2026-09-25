<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Which social providers may be offered right now. A button is shown only when ALL hold:
 *  1. listed in SOCIAL_ENABLED_PROVIDERS and known to config/social.php,
 *  2. credentials are configured (or the local fake provider is active),
 *  3. the API says the provider is enabled (`GET public/customers/social/providers`). Fail closed: if the API
 *     cannot tell us, no buttons are shown and the email+password form still works.
 */
class SocialProviders
{
    public function __construct(private readonly SocialAuthApi $api) {}

    /** The fake provider is a dev/test tool and is hard-disabled in production, whatever the env says. */
    public function fakeEnabled(): bool
    {
        if (! config('social.fake')) {
            return false;
        }
        if (app()->environment('production')) {
            static $warned = false;
            if (! $warned) {
                $warned = true;
                Log::warning('SOCIAL_FAKE is set but ignored in production');
            }

            return false;
        }

        return true;
    }

    public function label(string $provider): string
    {
        return (string) config("social.providers.$provider.label", ucfirst($provider));
    }

    public function known(string $provider): bool
    {
        return is_array(config("social.providers.$provider"));
    }

    public function configured(string $provider): bool
    {
        if (! $this->known($provider)) {
            return false;
        }

        return $this->fakeEnabled()
            || (filled(config("social.providers.$provider.client_id")) && filled(config("social.providers.$provider.client_secret")));
    }

    /** @return list<string> */
    public function local(): array
    {
        return array_values(array_filter(
            array_unique((array) config('social.enabled')),
            fn ($p) => $this->configured((string) $p),
        ));
    }

    /**
     * Providers to render buttons for (site config AND API).
     *
     * @return list<array{key: string, label: string}>
     */
    public function available(): array
    {
        $local = $this->local();
        if ($local === []) {
            return [];
        }

        $remote = $this->remote();
        $out = [];
        foreach ($local as $p) {
            if (in_array($p, $remote, true)) {
                $out[] = ['key' => $p, 'label' => $this->label($p)];
            }
        }

        return $out;
    }

    public function isAvailable(string $provider): bool
    {
        return in_array($provider, array_column($this->available(), 'key'), true);
    }

    /** @return list<string> */
    private function remote(): array
    {
        $key = 'social.api_providers.'.sha1(config('r007.api.base_url').(config('r007.mock') ? 'mock' : ''));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $list = $this->api->providers();
        } catch (\Throwable) {
            return [];
        }
        Cache::put($key, $list, (int) config('social.providers_cache_seconds'));

        return $list;
    }
}

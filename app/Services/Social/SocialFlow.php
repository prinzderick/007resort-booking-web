<?php

namespace App\Services\Social;

use Illuminate\Contracts\Session\Session;

/**
 * Server-side (encrypted session) state of an in-progress social sign-in. Holds only the provider PROFILE
 * (never provider tokens), and forgets it after `pending_ttl_minutes` or when the flow ends.
 */
class SocialFlow
{
    public const FLOW = 'social.flow';      // set at /redirect: intent, origin, return_to (consumed by the callback)

    public const PENDING = 'social.pending'; // identity waiting for the consent step

    public const LINK = 'social.link';       // identity waiting for the emailed link code

    public const COMPLETE = 'social.complete'; // signed in, still completing the profile

    public function __construct(private readonly Session $session) {}

    /** @param array<string, mixed> $data */
    public function put(string $key, array $data): void
    {
        $this->session->put($key, $data + ['expires' => now()->addMinutes((int) config('social.pending_ttl_minutes'))->getTimestamp()]);
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        $v = $this->session->get($key);
        if (! is_array($v) || (int) ($v['expires'] ?? 0) < time()) {
            $this->session->forget($key);

            return null;
        }

        return $v;
    }

    /** @return array<string, mixed> */
    public function pullFlow(): array
    {
        $v = $this->session->pull(self::FLOW);

        return is_array($v) ? $v : [];
    }

    public function forget(string ...$keys): void
    {
        $this->session->forget($keys);
    }

    public function clearAll(): void
    {
        $this->session->forget([self::FLOW, self::PENDING, self::LINK, self::COMPLETE]);
    }

    public static function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return 'your email address';
        }
        [$l, $d] = explode('@', $email, 2);

        return mb_substr($l, 0, 1).str_repeat('•', 3).'@'.$d;
    }
}

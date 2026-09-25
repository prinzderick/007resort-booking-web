<?php

namespace App\Support;

/**
 * Open-redirect guard for `return_to`: only same-site RELATIVE paths ("/sports/x?y=1") are accepted. Anything with
 * a scheme, host, protocol-relative prefix, backslash, control character or auth-flow loop is refused.
 */
final class SafeReturn
{
    public static function path(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2000) {
            return null;
        }
        if (! str_starts_with($value, '/') || str_starts_with($value, '//') || str_contains($value, '\\')) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) || preg_match('/%(0[0-9a-f]|1[0-9a-f]|7f|5c)/i', $value)) {
            return null;
        }
        // Parsing must yield no scheme/host (e.g. "/\t/evil", "/@evil").
        $parts = parse_url($value);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user'])) {
            return null;
        }
        // Never bounce back into the authentication machinery itself.
        if (preg_match('#^/(auth|login|register|verify|logout)(/|\?|$)#', $value)) {
            return null;
        }

        return $value;
    }

    /** Accepts a same-host absolute URL too (Laravel's `url.intended` stores absolute URLs) and returns its path+query. */
    public static function fromIntended(mixed $value, string $host): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (str_starts_with($value, '/')) {
            return self::path($value);
        }
        $p = parse_url($value);
        if ($p === false || empty($p['host']) || strtolower($p['host']) !== strtolower($host) || isset($p['user'])) {
            return null;
        }

        return self::path(($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : ''));
    }
}

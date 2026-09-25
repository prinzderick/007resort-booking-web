<?php

namespace App\Support;

/** Provider avatars are third-party images: https only, from an allow-list of hosts (also mirrored in the CSP). */
final class Avatar
{
    public static function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 1000) {
            return null;
        }
        $p = parse_url($url);
        if ($p === false || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['port'])) {
            return null;
        }
        $host = strtolower($p['host']);

        return self::hostAllowed($host) ? $url : null;
    }

    public static function hostAllowed(string $host): bool
    {
        foreach ((array) config('social.avatar_hosts') as $origin) {
            if (strtolower((string) parse_url($origin, PHP_URL_HOST)) === $host) {
                return true;
            }
        }
        foreach ((array) config('social.avatar_host_suffixes') as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** CSP img-src origins: exact hosts plus wildcard suffixes. @return list<string> */
    public static function cspOrigins(): array
    {
        $out = (array) config('social.avatar_hosts');
        foreach ((array) config('social.avatar_host_suffixes') as $s) {
            $out[] = 'https://*'.$s;
        }

        return array_values(array_unique($out));
    }

    public static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ini = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $ini !== '' ? $ini : '?';
    }
}

<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Display-timezone helpers. The API speaks UTC; humans speak Africa/Lagos. */
final class Lagos
{
    public static function tz(): string
    {
        return (string) config('r007.display_timezone', 'Africa/Lagos');
    }

    public static function parse(?string $utc): ?CarbonImmutable
    {
        return $utc ? CarbonImmutable::parse($utc)->setTimezone(self::tz()) : null;
    }

    /** Start of the given local date (Y-m-d), or null when invalid. */
    public static function dayStart(?string $date): ?CarbonImmutable
    {
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $date, self::tz()) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::tz())->startOfDay();
    }

    public static function utcIso(CarbonImmutable $t): string
    {
        return $t->utc()->format('Y-m-d\TH:i:s\Z');
    }
}

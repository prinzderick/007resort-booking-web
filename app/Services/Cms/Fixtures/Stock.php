<?php

namespace App\Services\Cms\Fixtures;

/** Fixture-only: resolves a stock photo key (see scripts/stock-variants.py) to a CMS-shaped `media` object. */
final class Stock
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $all = null;

    /** @return array<string, mixed> */
    public static function media(string $key, ?string $alt = null): array
    {
        self::$all ??= json_decode((string) file_get_contents(resource_path('cms-fixtures/stock.json')), true) ?: [];
        $m = self::$all[$key] ?? self::$all['hero-01'] ?? ['url' => '', 'variants' => [], 'width' => 1600, 'height' => 1067];
        if ($alt !== null) {
            $m['alt'] = $alt;
        }

        return $m;
    }

    /** @return list<string> */
    public static function keys(string $prefix = ''): array
    {
        self::media('hero-01');

        return array_values(array_filter(array_keys(self::$all ?? []), fn ($k) => str_starts_with($k, $prefix)));
    }
}

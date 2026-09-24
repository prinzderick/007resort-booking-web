<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * "Open now" computed in the property's timezone (Africa/Lagos) from CMS hours (`hours.weekly` MON..SUN,
 * `hours.holidays` overrides). Display data only, never booking authority.
 */
final class OpenHours
{
    private const KEYS = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

    /**
     * @param  array<string, mixed>  $hours
     * @return array{known: bool, open: bool, label: string, short: string, today: ?array<string, mixed>, week: list<array<string, mixed>>}
     */
    public static function status(array $hours, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(Lagos::tz());
        $week = self::week($hours);
        if ($week === []) {
            return ['known' => false, 'open' => false, 'label' => '', 'short' => '', 'today' => null, 'week' => []];
        }
        $slot = fn (CarbonImmutable $d) => self::forDate($hours, $week, $d);
        $today = $slot($now);
        $minutes = $now->hour * 60 + $now->minute;

        if ($today && ! $today['closed'] && $minutes >= $today['openMin'] && $minutes < $today['closeMin']) {
            return ['known' => true, 'open' => true, 'label' => 'Open now, until '.self::fmt($today['close']), 'short' => 'Open now', 'today' => $today, 'week' => $week];
        }
        if ($today && ! $today['closed'] && $minutes < $today['openMin']) {
            return ['known' => true, 'open' => false, 'label' => 'Closed, opens '.self::fmt($today['open']), 'short' => 'Opens '.self::fmt($today['open']), 'today' => $today, 'week' => $week];
        }
        for ($i = 1; $i <= 7; $i++) {
            $d = $now->addDays($i);
            $n = $slot($d);
            if ($n && ! $n['closed']) {
                $when = $i === 1 ? 'tomorrow' : $d->format('l');

                return ['known' => true, 'open' => false, 'label' => 'Closed, opens '.$when.' '.self::fmt($n['open']), 'short' => 'Closed', 'today' => $today, 'week' => $week];
            }
        }

        return ['known' => true, 'open' => false, 'label' => 'Closed', 'short' => 'Closed', 'today' => $today, 'week' => $week];
    }

    /** @return list<array<string, mixed>> */
    public static function week(array $hours): array
    {
        $by = [];
        foreach ((array) ($hours['weekly'] ?? []) as $d) {
            $by[strtoupper((string) ($d['day'] ?? ''))] = $d;
        }
        $out = [];
        foreach (self::KEYS as $i => $k) {
            if (! isset($by[$k])) {
                continue;
            }
            $d = $by[$k];
            $closed = (bool) ($d['closed'] ?? false) || empty($d['open']) || empty($d['close']);
            $out[] = ['key' => $k, 'iso' => $i + 1, 'name' => ucfirst(strtolower(CarbonImmutable::parse('monday')->addDays($i)->format('l'))), 'closed' => $closed, 'open' => $d['open'] ?? null, 'close' => $d['close'] ?? null,
                'openMin' => $closed ? 0 : self::min($d['open']), 'closeMin' => $closed ? 0 : self::min($d['close'])];
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $week @return array<string, mixed>|null */
    private static function forDate(array $hours, array $week, CarbonImmutable $d): ?array
    {
        foreach ((array) ($hours['holidays'] ?? []) as $h) {
            if (($h['date'] ?? null) === $d->format('Y-m-d')) {
                $closed = (bool) ($h['closed'] ?? false) || empty($h['open']);

                return ['closed' => $closed, 'open' => $h['open'] ?? null, 'close' => $h['close'] ?? null, 'openMin' => $closed ? 0 : self::min($h['open']), 'closeMin' => $closed ? 0 : self::min($h['close'])];
            }
        }
        foreach ($week as $w) {
            if ($w['iso'] === $d->dayOfWeekIso) {
                return $w;
            }
        }

        return null;
    }

    private static function min(?string $hhmm): int
    {
        [$h, $m] = array_pad(explode(':', (string) $hhmm), 2, 0);

        return ((int) $h) * 60 + (int) $m;
    }

    public static function fmt(?string $hhmm): string
    {
        if (! $hhmm) {
            return '';
        }
        [$h, $m] = array_pad(explode(':', $hhmm), 2, 0);
        $h = (int) $h;
        $m = (int) $m;
        if ($h === 23 && $m === 59) {
            return 'midnight';
        }
        $suffix = $h >= 12 && $h < 24 ? 'pm' : 'am';
        $h12 = $h % 12 ?: 12;

        return $h12.($m ? ':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '').$suffix;
    }
}

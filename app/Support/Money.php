<?php

namespace App\Support;

/**
 * Presentation-only money formatting for API decimal strings. Never converts
 * to float: the string is split and grouped textually.
 */
final class Money
{
    public static function format(?string $amount, string $currency = 'NGN'): string
    {
        if ($amount === null || $amount === '' || ! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $amount, $m)) {
            return '-';
        }

        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', ltrim($m[2], '0') ?: '0');
        $decimals = substr(($m[3] ?? '').'00', 0, 2);
        $fraction = $decimals === '00' ? '' : $decimals;
        $symbol = $currency === 'NGN' ? "\u{20A6}" : $currency.' ';

        return $m[1].$symbol.$whole.($fraction !== '' ? '.'.$fraction : '');
    }

    /** Minor units (kobo) as an integer string for the front-end estimate. */
    public static function minor(?string $amount): string
    {
        if ($amount === null || ! preg_match('/^(\d+)(?:\.(\d+))?$/', $amount, $m)) {
            return '0';
        }

        return ltrim($m[1].substr(($m[2] ?? '').'00', 0, 2), '0') ?: '0';
    }
}

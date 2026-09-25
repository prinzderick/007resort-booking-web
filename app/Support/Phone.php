<?php

namespace App\Support;

/**
 * Nigerian-friendly phone handling. Accepts what people actually type
 * (0803 123 4567, 803 123 4567, +234 803 123 4567, 234-803-123-4567) and
 * normalises to E.164 (+2348031234567). Other countries: a leading "+" and
 * 8-15 digits is accepted as typed. Presentation/validation only.
 */
final class Phone
{
    /** @return string|null E.164 or null when it cannot be a real number */
    public static function normalize(?string $input): ?string
    {
        $raw = trim((string) $input);
        if ($raw === '' || preg_match('/[^\d\s()+.\-]/', $raw)) {
            return null;
        }
        $international = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        }

        if ($international && ! str_starts_with($digits, '234')) {
            return preg_match('/^[1-9]\d{7,14}$/', $digits) ? '+'.$digits : null;
        }
        if (str_starts_with($digits, '234')) {
            $digits = substr($digits, 3);
            $digits = ltrim($digits, '0') === $digits ? $digits : substr($digits, 1); // +234 0803... typo
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Nigerian numbers: 10 national digits, mobile ranges start 7/8/9.
        return preg_match('/^[789]\d{9}$/', $digits) ? '+234'.$digits : null;
    }

    /** "+234 803 123 4567" for display; unknown shapes are returned as given. */
    public static function display(?string $e164): string
    {
        $v = (string) $e164;
        if (preg_match('/^\+234(\d{3})(\d{3})(\d{4})$/', $v, $m)) {
            return "+234 {$m[1]} {$m[2]} {$m[3]}";
        }

        return $v;
    }

    /** Mask for confirmations: +234 803 *** 4567 */
    public static function mask(?string $e164): string
    {
        return preg_replace('/^(\+234 \d{3}) \d{3}/', '$1 ***', self::display($e164)) ?? '';
    }
}

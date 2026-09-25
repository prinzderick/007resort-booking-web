<?php

namespace App\Support;

/** Nigerian mobile numbers: 0803 123 4567, 803 123 4567, 234803..., +234 803 123 4567 -> +2348031234567. */
final class NigerianPhone
{
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/[\s().-]/', '', (string) $input);
        if ($digits === null || $digits === '') {
            return null;
        }
        if (! preg_match('/^(?:\+?234|0)?([789]\d{9})$/', $digits, $m)) {
            return null;
        }

        return '+234'.$m[1];
    }
}

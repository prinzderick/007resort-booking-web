<?php

namespace App\Support;

/** Masks contact details shown back to a visitor so a shared screen does not leak them. */
final class Mask
{
    public static function email(?string $email): string
    {
        $email = (string) $email;
        if (! str_contains($email, '@')) {
            return '';
        }
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).str_repeat('*', max(2, min(6, mb_strlen($local) - 1))).'@'.$domain;
    }
}

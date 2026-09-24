<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/** Small presentation helpers for CMS strings. */
final class Text
{
    /**
     * Headline with an accent word. Editors may mark it with *asterisks* ("Your weekend starts *here.*"); when they
     * do not, the last word becomes the italic accent. Always HTML-escaped.
     */
    public static function accent(?string $s, bool $auto = true): HtmlString
    {
        $s = trim((string) $s);
        if ($s === '') {
            return new HtmlString('');
        }
        if (preg_match('/\*(.+?)\*/u', $s)) {
            $out = preg_replace_callback('/\*(.+?)\*/u', fn ($m) => "\0".$m[1]."\1", $s);
            $out = e($out);

            return new HtmlString(str_replace(["\0", "\1"], ['<i>', '</i>'], $out));
        }
        if ($auto && preg_match('/^(.*\s)(\S+)$/us', $s, $m)) {
            return new HtmlString(e($m[1]).'<i>'.e($m[2]).'</i>');
        }

        return new HtmlString(e($s));
    }

    /** The headline without accent markup (for <title>, alt text, JSON-LD). */
    public static function plain(?string $s): string
    {
        return trim(str_replace('*', '', (string) $s));
    }

    /** Leading number of a CMS stat value such as "26+" or "1,200" for the count-up animation. @return array{n: float, rest: string, raw: string}|null */
    public static function firstNumber(?string $s): ?array
    {
        if (preg_match('/^\s*([\d,]+(?:\.\d+)?)(.*)$/u', (string) $s, $m)) {
            return ['n' => (float) str_replace(',', '', $m[1]), 'rest' => trim($m[2]), 'raw' => $m[1]];
        }

        return null;
    }

    public static function digits(?string $s): string
    {
        return preg_replace('/\D+/', '', (string) $s) ?? '';
    }

    public static function excerpt(?string $html, int $max = 160): string
    {
        $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html))) ?? '');

        return mb_strlen($t) > $max ? rtrim(mb_substr($t, 0, $max - 1)).'…' : $t;
    }
}

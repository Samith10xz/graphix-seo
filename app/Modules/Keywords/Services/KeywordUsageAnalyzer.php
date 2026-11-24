<?php

namespace App\Modules\Keywords\Services;

/**
 * Checks where a keyword is used on one page and scores it 0–100.
 * Pure logic (no database), so it is fast and easy to test.
 */
class KeywordUsageAnalyzer
{
    /** Points per placement. Sum = 100. */
    public const WEIGHTS = [
        'title' => 20,
        'h1' => 15,
        'meta' => 10,
        'subheading' => 10,
        'url' => 10,
        'first_100_words' => 10,
        'density' => 10,
        'image_alt' => 5,
        'internal_anchor' => 10,
    ];

    public const DENSITY_MIN = 0.5; // percent
    public const DENSITY_MAX = 3.0;

    /**
     * @param  array{url:string, title?:?string, meta_description?:?string, h1?:list<string>, headings?:list<array{level:int,text:string}>,
     *               body_text?:?string, images?:list<array{alt?:?string}>, anchors?:list<string>}  $page
     * @return array{score:int, checks:array<string, mixed>}
     */
    public function analyze(string $keyword, array $page): array
    {
        $kw = self::normalize($keyword);
        $kwWords = count(self::words($kw));
        if ($kw === '' || $kwWords === 0) {
            return ['score' => 0, 'checks' => []];
        }

        $body = self::normalize((string) ($page['body_text'] ?? ''));
        $bodyWords = self::words($body);

        $subheadings = array_column(array_filter(
            $page['headings'] ?? [],
            fn ($h) => ($h['level'] ?? 0) >= 2,
        ), 'text');

        $occurrences = $kw !== '' ? substr_count(' '.$body.' ', ' '.$kw.' ') : 0;
        $density = count($bodyWords) > 0 ? round($occurrences * $kwWords / count($bodyWords) * 100, 2) : 0.0;

        $checks = [
            'title' => self::contains($page['title'] ?? '', $kw),
            'h1' => self::anyContains($page['h1'] ?? [], $kw),
            'meta' => self::contains($page['meta_description'] ?? '', $kw),
            'subheading' => self::anyContains($subheadings, $kw),
            'url' => self::urlContains($page['url'], $kw),
            'first_100_words' => self::contains(implode(' ', array_slice($bodyWords, 0, 100)), $kw),
            'density' => $density >= self::DENSITY_MIN && $density <= self::DENSITY_MAX,
            'image_alt' => self::anyContains(array_column($page['images'] ?? [], 'alt'), $kw),
            'internal_anchor' => self::anyContains($page['anchors'] ?? [], $kw),
        ];

        $score = 0;
        foreach (self::WEIGHTS as $check => $points) {
            if ($checks[$check]) {
                $score += $points;
            }
        }

        $checks['density_percent'] = $density;
        $checks['occurrences'] = $occurrences;
        $checks['title_position'] = self::position($page['title'] ?? '', $kw);

        return ['score' => $score, 'checks' => $checks];
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** @return list<string> */
    private static function words(string $normalized): array
    {
        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    private static function contains(?string $haystack, string $kw): bool
    {
        return str_contains(' '.self::normalize((string) $haystack).' ', ' '.$kw.' ');
    }

    private static function anyContains(array $items, string $kw): bool
    {
        foreach ($items as $item) {
            if (is_string($item) && self::contains($item, $kw)) {
                return true;
            }
        }

        return false;
    }

    /** URL slugs use hyphens: "/send-parcel-to-uk/" matches "send parcel to uk". */
    private static function urlContains(string $url, string $kw): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return self::contains(str_replace(['-', '_', '/'], ' ', $path), $kw);
    }

    /** Word position of the keyword in the title (1 = first), null if absent. */
    private static function position(?string $title, string $kw): ?int
    {
        $t = ' '.self::normalize((string) $title).' ';
        $pos = mb_strpos($t, ' '.$kw.' ');

        return $pos === false ? null : count(self::words(trim(mb_substr($t, 0, $pos)))) + 1;
    }
}

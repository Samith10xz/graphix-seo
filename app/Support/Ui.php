<?php

namespace App\Support;

/** Small formatting helpers shared by the Blade screens. */
final class Ui
{
    /** Pill colour class for a 0–100 score. */
    public static function scoreClass(?int $score): string
    {
        return match (true) {
            $score === null => 'neutral',
            $score >= 80 => 'ok',
            $score >= 50 => 'warn',
            default => 'crit',
        };
    }

    /** Heatmap cell class for a keyword usage score. */
    public static function heatClass(?int $score): string
    {
        return match (true) {
            $score === null => 'e',
            $score >= 75 => 'g',
            $score >= 40 => 'a',
            default => 'r',
        };
    }

    public static function statusClass(?int $status): string
    {
        return match (true) {
            $status === null => 'neutral',
            $status >= 200 && $status < 300 => 'ok',
            $status >= 300 && $status < 400 => 'warn',
            default => 'crit',
        };
    }

    public static function severityClass(string $severity): string
    {
        return ['critical' => 'crit', 'warning' => 'warn'][$severity] ?? 'neutral';
    }

    public static function areaLabel(string $area): string
    {
        return ['seo' => 'SEO', 'ai' => 'AI', 'speed' => 'Speed'][$area] ?? ucfirst($area);
    }

    /** Path + query of a URL, for compact tables ("/services/"). */
    public static function path(?string $url): string
    {
        if ($url === null || $url === '') {
            return '—';
        }
        $parts = parse_url($url);
        $path = ($parts['path'] ?? '') ?: '/';

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    public static function num(int|float|null $n): string
    {
        return $n === null ? '—' : number_format($n);
    }

    public static function seconds(?int $ms): string
    {
        return $ms === null ? '—' : number_format($ms / 1000, 2).'s';
    }

    /** Percentage width for a bar, safe for zero totals. */
    public static function pct(int|float $part, int|float $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0;
    }

    /** SVG stroke-dasharray for a ring of radius $r showing $score out of 100. */
    public static function ring(?int $score, float $r = 34): string
    {
        $c = 2 * M_PI * $r;

        return round(($score ?? 0) / 100 * $c, 1).' '.round($c, 1);
    }
}

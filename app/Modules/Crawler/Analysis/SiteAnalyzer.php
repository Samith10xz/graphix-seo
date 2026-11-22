<?php

namespace App\Modules\Crawler\Analysis;

/**
 * Cross-page checks: duplicates, broken links, robots/sitemap/llms.txt and AI-bot access.
 * PHP port of engine/app/audit.py analyze_site() with the same output shape.
 */
final class SiteAnalyzer
{
    public const AI_BOTS = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'Bingbot'];

    /**
     * @param  list<array>  $pages  page arrays (url, title, description, indexable, scores, issues, redirect?)
     * @param  list<array{from:string,to:string,status:int}>  $broken
     */
    public function analyze(array $pages, ?string $robotsTxt, bool $sitemapFound, bool $llmsFound, array $broken): array
    {
        $issues = [];
        $add = function (string $area, string $severity, string $code, string $message) use (&$issues) {
            $issues[] = compact('area', 'severity', 'code', 'message');
        };

        if ($robotsTxt === null) {
            $add('seo', 'warning', 'robots_missing', 'No robots.txt file.');
        }
        if (! $sitemapFound) {
            $add('seo', 'warning', 'sitemap_missing', 'No XML sitemap found (checked robots.txt and /sitemap.xml).');
        }
        if (! $llmsFound) {
            $add('ai', 'notice', 'llms_txt_missing', 'No /llms.txt file to guide AI assistants to your key pages.');
        }

        $blocked = [];
        if ($robotsTxt && $pages) {
            $robots = new RobotsTxt($robotsTxt);
            $home = $pages[0]['url'];
            foreach (self::AI_BOTS as $bot) {
                if (! $robots->allows($bot, $home)) {
                    $blocked[] = $bot;
                }
            }
            if ($blocked) {
                $add('ai', 'warning', 'ai_bots_blocked',
                    'robots.txt blocks AI crawlers: '.implode(', ', $blocked).". You won't be cited by those engines.");
            }
        }

        $dupTitles = $this->duplicates($pages, 'title');
        $dupDesc = $this->duplicates($pages, 'description');
        if ($dupTitles) {
            $add('seo', 'warning', 'duplicate_titles', count($dupTitles).' title(s) are used on more than one page.');
        }
        if ($dupDesc) {
            $add('seo', 'notice', 'duplicate_descriptions', count($dupDesc).' meta description(s) are duplicated.');
        }
        if ($broken) {
            $add('seo', 'critical', 'broken_links', count($broken).' broken link(s) found.');
        }

        $scored = array_values(array_filter($pages, fn ($p) => empty($p['redirect'])));
        $n = max(1, count($scored));

        $totals = ['critical' => 0, 'warning' => 0, 'notice' => 0];
        foreach ($pages as $p) {
            foreach ($p['issues'] ?? [] as $i) {
                $totals[$i['severity']]++;
            }
        }
        foreach ($issues as $i) {
            $totals[$i['severity']]++;
        }

        $penalty = fn (string $area) => array_sum(array_map(
            fn ($i) => $i['area'] === $area ? PageAnalyzer::WEIGHTS[$i['severity']] : 0, $issues));
        $seo = $scored ? (int) round(array_sum(array_map(fn ($p) => $p['scores']['seo'] ?? 0, $scored)) / $n) : 0;
        $ai = $scored ? (int) round(array_sum(array_map(fn ($p) => $p['scores']['ai'] ?? 0, $scored)) / $n) : 0;

        return [
            'pages_crawled' => count($scored),
            'redirects' => count($pages) - count($scored),
            'scores' => ['seo' => max(0, $seo - $penalty('seo')), 'ai' => max(0, $ai - $penalty('ai'))],
            'issue_totals' => $totals,
            'blocked_ai_bots' => $blocked,
            'duplicate_titles' => $dupTitles,
            'duplicate_descriptions' => $dupDesc,
            'broken_links' => $broken,
            'issues' => $issues,
        ];
    }

    private function duplicates(array $pages, string $field): array
    {
        $seen = [];
        foreach ($pages as $p) {
            $v = mb_strtolower(trim((string) ($p[$field] ?? '')));
            if ($v !== '' && ! empty($p['indexable'])) {
                $seen[$v][] = $p['url'];
            }
        }
        $out = [];
        foreach ($seen as $value => $urls) {
            if (count($urls) > 1) {
                $out[] = ['value' => $value, 'urls' => $urls];
            }
        }

        return $out;
    }
}

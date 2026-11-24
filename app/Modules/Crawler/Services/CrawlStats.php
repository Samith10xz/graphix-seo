<?php

namespace App\Modules\Crawler\Services;

use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Models\CrawlFrontier;
use App\Modules\Crawler\Models\Issue;
use App\Modules\Crawler\Models\Link;

/** Aggregates for the crawl charts. All queries are portable (MySQL + SQLite). */
class CrawlStats
{
    public const SEVERITY_ORDER = ['critical' => 0, 'warning' => 1, 'notice' => 2];

    /** @return array{2xx:int,3xx:int,4xx:int,5xx:int,other:int,total:int} */
    public function statusCodes(Crawl $crawl): array
    {
        $row = $crawl->pages()->toBase()->selectRaw(
            'SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) AS s2,
             SUM(CASE WHEN status_code BETWEEN 300 AND 399 THEN 1 ELSE 0 END) AS s3,
             SUM(CASE WHEN status_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) AS s4,
             SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) AS s5,
             COUNT(*) AS total'
        )->first();

        $out = ['2xx' => (int) $row->s2, '3xx' => (int) $row->s3, '4xx' => (int) $row->s4, '5xx' => (int) $row->s5];
        $out['total'] = (int) $row->total;
        $out['other'] = max(0, $out['total'] - array_sum(array_slice($out, 0, 4)));

        return $out;
    }

    /** Pages per click depth, with 4+ grouped. @return array<string,int> */
    public function depths(Crawl $crawl): array
    {
        $rows = $crawl->pages()->toBase()
            ->whereNotNull('depth')
            ->selectRaw('depth, COUNT(*) AS n')
            ->groupBy('depth')
            ->pluck('n', 'depth');

        $out = ['0' => 0, '1' => 0, '2' => 0, '3' => 0, '4+' => 0];
        foreach ($rows as $depth => $n) {
            $out[$depth >= 4 ? '4+' : (string) $depth] += (int) $n;
        }

        return $out;
    }

    /** @return array{indexable:int,canonicalised:int,noindex:int,other:int} */
    public function indexability(Crawl $crawl): array
    {
        $html = $crawl->pages()->whereBetween('status_code', [200, 299]);
        $indexable = (clone $html)->where('indexable', true)->count();
        $canonical = $this->pagesWithIssue($crawl, 'canonical_other');
        $noindex = $this->pagesWithIssue($crawl, 'noindex');
        $nonIndexable = (clone $html)->where('indexable', false)->count();

        return [
            'indexable' => $indexable,
            'canonicalised' => $canonical,
            'noindex' => $noindex,
            'other' => max(0, $nonIndexable - $canonical - $noindex),
        ];
    }

    /** Response-time histogram buckets (ms). @return array<string,int> */
    public function responseTimes(Crawl $crawl): array
    {
        return $this->buckets($crawl, 'load_ms', ['<0.3s' => 300, '0.6s' => 600, '1s' => 1000, '2s' => 2000], '3s+');
    }

    /** Word-count histogram for HTML pages. @return array<string,int> */
    public function wordCounts(Crawl $crawl): array
    {
        return $this->buckets($crawl, 'word_count', ['<300' => 300, '600' => 600, '1k' => 1000, '2k' => 2000], '2k+', true);
    }

    /** @return array{critical:int,warning:int,notice:int} issue rows by severity */
    public function severityCounts(Crawl $crawl): array
    {
        $rows = Issue::where('crawl_id', $crawl->id)->toBase()
            ->selectRaw('severity, COUNT(*) AS n')->groupBy('severity')->pluck('n', 'severity');

        return [
            'critical' => (int) ($rows['critical'] ?? 0),
            'warning' => (int) ($rows['warning'] ?? 0),
            'notice' => (int) ($rows['notice'] ?? 0),
        ];
    }

    /**
     * One row per issue code: severity, area, pages affected (0 = site-wide only), a sample message.
     *
     * @return list<array{code:string,severity:string,area:string,pages:int,site:bool,message:string}>
     */
    public function issueGroups(Crawl $crawl, ?string $severity = null, ?string $area = null): array
    {
        $query = Issue::where('crawl_id', $crawl->id)->toBase()
            ->selectRaw('code, severity, area, COUNT(DISTINCT page_id) AS pages, SUM(CASE WHEN page_id IS NULL THEN 1 ELSE 0 END) AS site, MIN(message) AS message')
            ->groupBy('code', 'severity', 'area');
        if ($severity) {
            $query->where('severity', $severity);
        }
        if ($area) {
            $query->where('area', $area);
        }

        return $query->get()
            ->map(fn ($r) => [
                'code' => $r->code, 'severity' => $r->severity, 'area' => $r->area,
                'pages' => (int) $r->pages, 'site' => (int) $r->site > 0, 'message' => (string) $r->message,
            ])
            ->sortBy([
                fn ($a, $b) => (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9),
                fn ($a, $b) => $b['pages'] <=> $a['pages'],
            ])
            ->values()
            ->all();
    }

    public function pagesWithIssue(Crawl $crawl, string $code): int
    {
        return Issue::where('crawl_id', $crawl->id)->where('code', $code)->whereNotNull('page_id')
            ->distinct()->count('page_id');
    }

    /** Broken link targets with how many pages link to them. */
    public function brokenTargets(Crawl $crawl, int $limit = 50): array
    {
        return Link::where('crawl_id', $crawl->id)->toBase()
            ->where('status_code', '>=', 400)
            ->selectRaw('to_url, status_code, COUNT(DISTINCT from_hash) AS sources')
            ->groupBy('to_url', 'status_code')
            ->orderByDesc('sources')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['url' => $r->to_url, 'status' => (int) $r->status_code, 'sources' => (int) $r->sources])
            ->all();
    }

    public function linkCount(Crawl $crawl): int
    {
        return Link::where('crawl_id', $crawl->id)->count();
    }

    /** Live progress for the polling banner. */
    public function progress(Crawl $crawl): array
    {
        $pending = CrawlFrontier::where('crawl_id', $crawl->id)->where('status', 'pending')->count();
        $latest = $crawl->pages()->latest('id')->value('url');
        $crawled = $crawl->pages()->count();
        $expected = max(1, min($crawl->max_pages, $crawled + $pending));
        $severity = $this->severityCounts($crawl);

        return [
            'id' => $crawl->id,
            'status' => $crawl->status,
            'running' => in_array($crawl->status, ['queued', 'running'], true),
            'pages_crawled' => $crawled,
            'pending' => $pending,
            'expected' => $expected,
            'percent' => $crawl->status === 'done' ? 100 : min(99, (int) round($crawled / $expected * 100)),
            'links' => $this->linkCount($crawl),
            'critical' => $severity['critical'],
            'current' => $latest,
            'seo_score' => $crawl->seo_score,
            'ai_score' => $crawl->ai_score,
            'error' => $crawl->status === 'failed' ? $crawl->error : null,
        ];
    }

    /** Pages added/removed and issue totals compared with the previous finished crawl. */
    public function changes(Crawl $crawl): ?array
    {
        $previous = Crawl::where('project_id', $crawl->project_id)
            ->where('status', 'done')->where('id', '<', $crawl->id)
            ->latest('id')->first();
        if (! $previous) {
            return null;
        }

        $now = $crawl->pages()->pluck('url_hash')->flip();
        $before = $previous->pages()->pluck('url_hash')->flip();

        return [
            'new' => $now->diffKeys($before)->count(),
            'removed' => $before->diffKeys($now)->count(),
            'issues_now' => Issue::where('crawl_id', $crawl->id)->count(),
            'issues_before' => Issue::where('crawl_id', $previous->id)->count(),
            'seo_before' => $previous->seo_score,
            'ai_before' => $previous->ai_score,
        ];
    }

    /** @param array<string,int> $edges label => upper bound (exclusive) */
    private function buckets(Crawl $crawl, string $column, array $edges, string $lastLabel, bool $htmlOnly = false): array
    {
        $cases = [];
        $lower = null;
        $i = 0;
        foreach ($edges as $upper) {
            $cond = $lower === null ? "{$column} < {$upper}" : "{$column} >= {$lower} AND {$column} < {$upper}";
            $cases[] = "SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END) AS b{$i}";
            $lower = $upper;
            $i++;
        }
        $cases[] = "SUM(CASE WHEN {$column} >= {$lower} THEN 1 ELSE 0 END) AS b{$i}";

        $query = $crawl->pages()->toBase()->whereNotNull($column);
        if ($htmlOnly) {
            $query->whereBetween('status_code', [200, 299]);
        }
        $row = (array) $query->selectRaw(implode(', ', $cases))->first();

        $labels = [...array_keys($edges), $lastLabel];
        $out = [];
        foreach ($labels as $j => $label) {
            $out[$label] = (int) ($row["b{$j}"] ?? 0);
        }

        return $out;
    }
}

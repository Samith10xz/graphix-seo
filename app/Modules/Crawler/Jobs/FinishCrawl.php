<?php

namespace App\Modules\Crawler\Jobs;

use App\Modules\Crawler\Analysis\SiteAnalyzer;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Models\Link;
use App\Modules\Crawler\Services\CrawlIngestService;
use App\Modules\Crawler\Services\Fetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** Last step of a PHP-engine crawl: check link statuses, run site-wide checks, hand over to ingest. */
class FinishCrawl implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;
    public int $timeout = 240;

    public function __construct(public int $crawlId) {}

    public function uniqueId(): string
    {
        return (string) $this->crawlId;
    }

    public function handle(Fetcher $fetcher, SiteAnalyzer $site, CrawlIngestService $ingest): void
    {
        $crawl = Crawl::find($this->crawlId);
        if (! $crawl || $crawl->status !== 'running') {
            return;
        }
        $state = $crawl->state ?? [];

        // Status of every crawled URL is known; check linked URLs that were not crawled (capped).
        $known = $crawl->pages()->pluck('status_code', 'url')->all();
        $unchecked = Link::where('crawl_id', $crawl->id)
            ->select('to_url')->distinct()->pluck('to_url')
            ->reject(fn ($u) => isset($known[$u]))
            ->take((int) config('crawler.max_link_checks', 300))
            ->values()->all();
        $statuses = array_merge($known, $fetcher->statuses($unchecked));

        $broken = [];
        Link::where('links.crawl_id', $crawl->id)
            ->join('pages', fn ($j) => $j->on('pages.url_hash', '=', 'links.from_hash')->on('pages.crawl_id', '=', 'links.crawl_id'))
            ->select('pages.url as from_url', 'links.to_url')
            ->orderBy('links.id')
            ->each(function ($row) use (&$broken, $statuses) {
                $s = $statuses[$row->to_url] ?? null;
                if ($s !== null && ($s === 0 || $s >= 400)) {
                    $broken[] = ['from' => $row->from_url, 'to' => $row->to_url, 'status' => (int) $s];
                }
            });

        $pages = $crawl->pages()->with('issues')->orderBy('id')->get()->map(fn ($p) => [
            'url' => $p->url,
            'title' => $p->title,
            'description' => $p->meta_description,
            'indexable' => $p->indexable,
            'redirect' => $p->status_code >= 300 && $p->status_code < 400,
            'scores' => ['seo' => $p->seo_score, 'ai' => $p->ai_score],
            'issues' => $p->issues->map->only(['area', 'severity', 'code'])->all(),
        ])->all();

        $result = $site->analyze($pages, $state['robots_txt'] ?? null, (bool) ($state['sitemap_found'] ?? false), (bool) ($state['llms_found'] ?? false), $broken);
        $result['link_status'] = array_filter($statuses, fn ($s) => $s === 0 || $s >= 300);

        $ingest->finish($crawl, $result);
        $crawl->frontier()->delete();
    }

    public function failed(Throwable $e): void
    {
        Crawl::whereKey($this->crawlId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
    }
}

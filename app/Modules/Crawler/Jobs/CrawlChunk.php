<?php

namespace App\Modules\Crawler\Jobs;

use App\Modules\Crawler\Analysis\PageAnalyzer;
use App\Modules\Crawler\Analysis\RobotsTxt;
use App\Modules\Crawler\Analysis\Url;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Models\Page;
use App\Modules\Crawler\Services\CrawlIngestService;
use App\Modules\Crawler\Services\Fetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * One slice of a PHP-engine crawl: fetch the next N frontier URLs (lowest depth first),
 * analyse them, store them, add newly found internal links, then queue the next slice
 * or the finish step.
 */
class CrawlChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 240;
    public array $backoff = [10, 30];

    public function __construct(public int $crawlId) {}

    public function handle(Fetcher $fetcher, PageAnalyzer $analyzer, CrawlIngestService $ingest): void
    {
        $crawl = Crawl::with('project')->find($this->crawlId);
        if (! $crawl || $crawl->status !== 'running') {
            return; // cancelled or already finished
        }

        $maxPages = min($crawl->max_pages, (int) config('crawler.max_pages', 1000));
        $state = $crawl->state ?? [];
        $robots = new RobotsTxt($state['robots_txt'] ?? null);
        $ua = (string) config('crawler.user_agent');

        $remaining = $maxPages - $crawl->pages()->count();
        $batch = $crawl->frontier()->where('status', 'pending')
            ->orderBy('depth')->orderBy('id')
            ->limit(max(0, min((int) config('crawler.chunk_size', 20), $remaining)))
            ->get();

        if ($batch->isEmpty()) {
            FinishCrawl::dispatch($crawl->id);

            return;
        }

        $crawl->frontier()->whereIn('id', $batch->pluck('id'))->update(['status' => 'fetching']);

        $allowed = $batch->filter(fn ($row) => $robots->allows($ua, $row->url));
        $crawl->frontier()->whereIn('id', $batch->diff($allowed)->pluck('id'))->update(['status' => 'skipped']);

        $fetched = $fetcher->many($allowed->pluck('url')->all());
        $pages = [];
        $newLinks = [];

        foreach ($allowed as $row) {
            $r = $fetched[$row->url];
            if ($r['error'] !== null) {
                continue; // unreachable: reported later through link checks
            }

            $final = Url::normalize($r['final_url'] ?: $row->url);
            if ($r['redirect_status'] !== null && $final !== $row->url) {
                $pages[] = $this->redirectPage($row->url, $r['redirect_status'], $final, $row->depth, $r['ms']);
                if ($this->crawlable($final, $state['host'])) {
                    $newLinks[$final] = $row->depth;
                }

                continue;
            }

            $contentType = strtolower(implode(',', (array) (array_change_key_case($r['headers'])['content-type'] ?? [])));
            if (! str_contains($contentType, 'text/html')) {
                continue;
            }

            $page = $analyzer->analyze($row->url, $r['status'], $r['headers'], $r['body'], $r['ms']);
            $page['depth'] = $row->depth;
            $pages[] = $page;

            foreach ($page['internal_links'] as $link) {
                if ($this->crawlable($link, $state['host'])) {
                    $newLinks[$link] ??= $row->depth + 1;
                }
            }
        }

        if ($pages) {
            $ingest->storePages($crawl, $pages);
        }

        // Queue newly found URLs (capped so the frontier cannot explode on huge sites).
        $room = $maxPages * 3 - $crawl->frontier()->count();
        if ($room > 0 && $newLinks) {
            $rows = [];
            foreach (array_slice($newLinks, 0, $room, true) as $url => $depth) {
                $rows[] = ['crawl_id' => $crawl->id, 'url' => mb_substr($url, 0, 2048), 'url_hash' => Page::hashUrl($url), 'depth' => $depth, 'status' => 'pending'];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                $crawl->frontier()->insertOrIgnore($chunk);
            }
        }

        $crawl->frontier()->whereIn('id', $allowed->pluck('id'))->update(['status' => 'done']);
        $crawl->update(['state' => array_merge($state, ['chunks' => ($state['chunks'] ?? 0) + 1])]);

        $morePending = $crawl->frontier()->where('status', 'pending')->exists();
        if ($morePending && $crawl->pages()->count() < $maxPages) {
            self::dispatch($crawl->id);
        } else {
            FinishCrawl::dispatch($crawl->id);
        }
    }

    public function failed(Throwable $e): void
    {
        Crawl::whereKey($this->crawlId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
    }

    private function crawlable(string $url, string $host): bool
    {
        $parts = parse_url($url);
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || strtolower($parts['host'] ?? '') !== $host) {
            return false;
        }
        $ext = strtolower(pathinfo($parts['path'] ?? '', PATHINFO_EXTENSION));

        return ! in_array($ext, config('crawler.skip_extensions', []), true);
    }

    /** Same shape as engine/app/crawler.py _redirect_page(). */
    private function redirectPage(string $url, int $status, string $target, int $depth, int $ms): array
    {
        return [
            'url' => $url, 'status' => $status, 'redirect' => true, 'final_url' => $target, 'depth' => $depth, 'load_ms' => $ms,
            'indexable' => false, 'title' => null, 'description' => null, 'canonical' => null, 'h1' => [], 'headings' => [],
            'word_count' => 0, 'images' => [], 'links' => [], 'internal_links' => [], 'external_links' => [],
            'schema_types' => [], 'ai_signals' => [], 'scores' => ['seo' => null, 'ai' => null],
            'issues' => [['area' => 'seo', 'severity' => 'notice', 'code' => 'redirect',
                'message' => "Redirects ({$status}) to {$target}. Update internal links to point to the final URL."]],
        ];
    }
}

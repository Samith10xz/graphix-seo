<?php

namespace App\Modules\Crawler\Engines;

use App\Contracts\CrawlEngine;
use App\Modules\Crawler\Analysis\RobotsTxt;
use App\Modules\Crawler\Analysis\Url;
use App\Modules\Crawler\Jobs\CrawlChunk;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Models\Page;
use App\Modules\Crawler\Services\Fetcher;
use Illuminate\Support\Facades\DB;

/**
 * Crawls inside Laravel: reads robots.txt / sitemap / llms.txt once, seeds the frontier
 * with the start URL, then queues CrawlChunk jobs (each fetches a few pages and queues
 * the next), so no single job runs long enough to hit shared-hosting limits.
 */
class PhpCrawlEngine implements CrawlEngine
{
    public function __construct(private Fetcher $fetcher) {}

    public function name(): string
    {
        return 'php';
    }

    public function start(Crawl $crawl): void
    {
        $startUrl = Url::normalize($crawl->project->url);
        $parts = parse_url($startUrl);
        $base = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        $robotsTxt = $this->fetcher->textOrNull($base.'/robots.txt');
        $robots = new RobotsTxt($robotsTxt);

        $sitemapFound = false;
        foreach (array_slice($robots->sitemaps() ?: [$base.'/sitemap.xml'], 0, 3) as $sitemap) {
            if ($this->fetcher->textOrNull($sitemap) !== null) {
                $sitemapFound = true;
                break;
            }
        }

        DB::transaction(function () use ($crawl, $startUrl, $robotsTxt, $sitemapFound, $base) {
            $crawl->frontier()->delete();
            $crawl->frontier()->create(['url' => $startUrl, 'url_hash' => Page::hashUrl($startUrl), 'depth' => 0]);

            $crawl->update([
                'status' => 'running',
                'engine' => 'php',
                'started_at' => now(),
                'state' => [
                    'host' => Url::host($startUrl),
                    'robots_txt' => $robotsTxt,
                    'sitemap_found' => $sitemapFound,
                    'llms_found' => $this->fetcher->textOrNull($base.'/llms.txt') !== null,
                    'chunks' => 0,
                ],
            ]);
        });

        CrawlChunk::dispatch($crawl->id);
    }
}

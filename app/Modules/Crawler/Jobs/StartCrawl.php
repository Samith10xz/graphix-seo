<?php

namespace App\Modules\Crawler\Jobs;

use App\Contracts\CrawlEngine;
use App\Modules\Crawler\Models\Crawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** Starts a crawl with whichever engine config('crawler.engine') selects. */
class StartCrawl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public array $backoff = [10, 60];

    public function __construct(public int $crawlId) {}

    public function handle(CrawlEngine $engine): void
    {
        $crawl = Crawl::with('project')->findOrFail($this->crawlId);
        if ($crawl->status !== 'queued') {
            return;
        }

        $engine->start($crawl);
    }

    public function failed(Throwable $e): void
    {
        Crawl::whereKey($this->crawlId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
    }
}

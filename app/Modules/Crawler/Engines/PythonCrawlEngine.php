<?php

namespace App\Modules\Crawler\Engines;

use App\Contracts\CrawlEngine;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Services\EngineClient;

/** Hands the crawl to the Python engine service (VPS). It posts results back to /api/internal. */
class PythonCrawlEngine implements CrawlEngine
{
    public function __construct(private EngineClient $client) {}

    public function name(): string
    {
        return 'python';
    }

    public function start(Crawl $crawl): void
    {
        $callbackBase = rtrim(config('services.engine.callback_url', 'http://nginx'), '/')."/api/internal/crawls/{$crawl->id}";

        $jobId = $this->client->startCrawl(
            $crawl->id,
            $crawl->project->url,
            $crawl->max_pages,
            $callbackBase,
            (bool) $crawl->project->setting('render_js', false),
        );

        $crawl->update(['status' => 'running', 'engine' => 'python', 'engine_job_id' => $jobId, 'started_at' => now()]);
    }
}

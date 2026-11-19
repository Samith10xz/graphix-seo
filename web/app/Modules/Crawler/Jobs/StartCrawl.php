<?php

namespace App\Modules\Crawler\Jobs;

use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Services\EngineClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class StartCrawl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public array $backoff = [10, 60];

    public function __construct(public int $crawlId) {}

    public function handle(EngineClient $engine): void
    {
        $crawl = Crawl::with('project')->findOrFail($this->crawlId);

        // Internal callback base: the engine reaches Laravel through nginx on the Docker network.
        $callbackBase = rtrim(config('services.engine.callback_url', 'http://nginx'), '/')."/api/internal/crawls/{$crawl->id}";

        $jobId = $engine->startCrawl(
            $crawl->id,
            $crawl->project->url,
            $crawl->max_pages,
            $callbackBase,
            (bool) $crawl->project->setting('render_js', false),
        );

        $crawl->update(['status' => 'running', 'engine_job_id' => $jobId, 'started_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        Crawl::whereKey($this->crawlId)->update(['status' => 'failed', 'error' => $e->getMessage()]);
    }
}

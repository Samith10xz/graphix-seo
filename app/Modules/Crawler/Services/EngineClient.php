<?php

namespace App\Modules\Crawler\Services;

use Illuminate\Support\Facades\Http;

/** Talks to the Python engine over the internal Docker network. */
class EngineClient
{
    /** Start a site crawl. The engine posts pages back to $callbackBase in batches. */
    public function startCrawl(int $crawlId, string $url, int $maxPages, string $callbackBase, bool $renderJs = false): string
    {
        $response = $this->http()->post('/v1/site-audits', [
            'crawl_id' => $crawlId,
            'url' => $url,
            'max_pages' => $maxPages,
            'render_js' => $renderJs,
            'callback_base' => $callbackBase,
        ])->throw();

        return (string) $response->json('job_id');
    }

    public function pageAudit(string $url): array
    {
        return $this->http()->post('/v1/page-audit', ['url' => $url])->throw()->json();
    }

    private function http()
    {
        return Http::baseUrl(config('services.engine.url'))
            ->withHeaders(['X-API-Key' => (string) config('services.engine.key')])
            ->timeout(config('services.engine.timeout', 60))
            ->acceptJson();
    }
}

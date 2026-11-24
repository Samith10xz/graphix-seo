<?php

namespace App\Modules\Keywords\Jobs;

use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Models\Link;
use App\Modules\Crawler\Models\Page;
use App\Modules\Keywords\Models\KeywordPageUsage;
use App\Modules\Keywords\Services\KeywordUsageAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * After a crawl: score every tracked keyword on every indexable HTML page.
 * Only scores > 0 are stored, so the keyword × page heatmap stays small.
 */
class AnalyzeKeywordUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 900;

    public function __construct(public int $crawlId) {}

    public function handle(KeywordUsageAnalyzer $analyzer): void
    {
        $crawl = Crawl::with('project.keywords')->findOrFail($this->crawlId);
        $keywords = $crawl->project->keywords;
        if ($keywords->isEmpty()) {
            return;
        }

        $crawl->pages()
            ->where('indexable', true)
            ->where('status_code', 200)
            ->select(['id', 'url', 'url_hash', 'title', 'meta_description', 'h1', 'headings', 'body_text', 'images'])
            ->chunkById(100, function ($pages) use ($crawl, $keywords, $analyzer) {
                $anchors = Link::where('crawl_id', $crawl->id)
                    ->where('internal', true)
                    ->whereIn('to_hash', $pages->pluck('url_hash'))
                    ->get(['to_hash', 'anchor'])
                    ->groupBy('to_hash');

                foreach ($pages as $page) {
                    $data = [
                        'url' => $page->url,
                        'title' => $page->title,
                        'meta_description' => $page->meta_description,
                        'h1' => $page->h1 ?? [],
                        'headings' => $page->headings ?? [],
                        'body_text' => $page->body_text,
                        'images' => $page->images ?? [],
                        'anchors' => ($anchors[$page->url_hash] ?? collect())->pluck('anchor')->filter()->all(),
                    ];

                    foreach ($keywords as $keyword) {
                        $result = $analyzer->analyze($keyword->keyword, $data);
                        $isTarget = $keyword->target_url && Page::hashUrl($keyword->target_url) === $page->url_hash;

                        if ($result['score'] > 0 || $isTarget) {
                            KeywordPageUsage::updateOrCreate(
                                ['keyword_id' => $keyword->id, 'page_id' => $page->id],
                                ['score' => $result['score'], 'checks' => $result['checks'], 'is_target' => $isTarget],
                            );
                        }
                    }
                }
            });
    }
}

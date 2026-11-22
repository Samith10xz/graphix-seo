<?php

namespace App\Modules\Crawler\Services;

use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Models\Issue;
use App\Modules\Crawler\Models\Link;
use App\Modules\Crawler\Models\Page;
use App\Modules\Keywords\Jobs\AnalyzeKeywordUsage;
use Illuminate\Support\Facades\DB;

/** Stores what the engine sends back: page batches while crawling, then the site summary. */
class CrawlIngestService
{
    private const BODY_TEXT_LIMIT = 100_000;

    /** @param list<array> $pages page dicts as produced by engine/app/audit.py */
    public function storePages(Crawl $crawl, array $pages): int
    {
        DB::transaction(function () use ($crawl, $pages) {
            foreach ($pages as $p) {
                $hash = Page::hashUrl($p['url']);

                $page = Page::updateOrCreate(
                    ['crawl_id' => $crawl->id, 'url_hash' => $hash],
                    [
                        'url' => $p['url'],
                        'status_code' => $p['status'] ?? null,
                        'indexable' => (bool) ($p['indexable'] ?? true),
                        'canonical' => $p['canonical'] ?? null,
                        'title' => $p['title'] ?? null,
                        'meta_description' => $p['description'] ?? null,
                        'h1' => $p['h1'] ?? [],
                        'headings' => $p['headings'] ?? [],
                        'word_count' => (int) ($p['word_count'] ?? 0),
                        'depth' => $p['depth'] ?? null,
                        'outlinks' => count($p['links'] ?? []),
                        'load_ms' => $p['load_ms'] ?? null,
                        'size_kb' => $p['size_kb'] ?? null,
                        'schema_types' => $p['schema_types'] ?? [],
                        'images' => $p['images'] ?? [],
                        'ai_signals' => $p['ai_signals'] ?? [],
                        'body_text' => mb_substr((string) ($p['body_text'] ?? ''), 0, self::BODY_TEXT_LIMIT),
                        'seo_score' => $p['scores']['seo'] ?? null,
                        'ai_score' => $p['scores']['ai'] ?? null,
                    ],
                );

                Link::where('crawl_id', $crawl->id)->where('from_hash', $hash)->delete();
                $rows = array_map(fn ($l) => [
                    'crawl_id' => $crawl->id,
                    'from_hash' => $hash,
                    'to_url' => mb_substr($l['url'], 0, 2048),
                    'to_hash' => Page::hashUrl($l['url']),
                    'anchor' => mb_substr((string) ($l['anchor'] ?? ''), 0, 512),
                    'internal' => (bool) ($l['internal'] ?? true),
                    'nofollow' => (bool) ($l['nofollow'] ?? false),
                ], $p['links'] ?? []);
                foreach (array_chunk($rows, 500) as $chunk) {
                    Link::insert($chunk);
                }

                $page->issues()->delete();
                foreach ($p['issues'] ?? [] as $i) {
                    $page->issues()->create([
                        'crawl_id' => $crawl->id, 'code' => $i['code'], 'area' => $i['area'],
                        'severity' => $i['severity'], 'message' => mb_substr($i['message'], 0, 1024),
                    ]);
                }
            }
        });

        $count = $crawl->pages()->count();
        $crawl->update(['pages_crawled' => $count]);

        return $count;
    }

    /** @param array $site the engine's analyze_site() output plus broken link statuses */
    public function finish(Crawl $crawl, array $site): void
    {
        DB::transaction(function () use ($crawl, $site) {
            // inlinks per page from the links table
            DB::statement(
                'UPDATE pages SET inlinks = (SELECT COUNT(*) FROM links WHERE links.crawl_id = pages.crawl_id AND links.to_hash = pages.url_hash AND links.internal = 1) WHERE crawl_id = ?',
                [$crawl->id],
            );

            foreach ($site['link_status'] ?? [] as $url => $status) {
                Link::where('crawl_id', $crawl->id)->where('to_hash', Page::hashUrl($url))->update(['status_code' => $status]);
            }

            Issue::where('crawl_id', $crawl->id)->whereNull('page_id')->delete();
            foreach ($site['issues'] ?? [] as $i) {
                Issue::create([
                    'crawl_id' => $crawl->id, 'page_id' => null, 'code' => $i['code'], 'area' => $i['area'],
                    'severity' => $i['severity'], 'message' => mb_substr($i['message'], 0, 1024),
                ]);
            }

            $crawl->update([
                'status' => 'done',
                'finished_at' => now(),
                'seo_score' => $site['scores']['seo'] ?? null,
                'ai_score' => $site['scores']['ai'] ?? null,
                'summary' => collect($site)->except(['issues', 'link_status'])->all(),
                'pages_crawled' => $crawl->pages()->count(),
            ]);
        });

        AnalyzeKeywordUsage::dispatch($crawl->id);
    }

    public function fail(Crawl $crawl, string $error): void
    {
        $crawl->update(['status' => 'failed', 'error' => mb_substr($error, 0, 2000), 'finished_at' => now()]);
    }
}

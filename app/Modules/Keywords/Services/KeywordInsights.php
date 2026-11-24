<?php

namespace App\Modules\Keywords\Services;

use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Models\Page;
use App\Modules\Keywords\Models\Keyword;
use App\Modules\Keywords\Models\KeywordPageUsage;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Collection;

/**
 * Turns keyword × page usage scores from the latest finished crawl into the
 * Keyword Tracker views: stats, heatmap, cannibalisation and orphans.
 */
class KeywordInsights
{
    public const WELL_PLACED = 75;
    public const COMPETING = 50;
    public const MAX_COLUMNS = 8;

    public const CHECK_LABELS = [
        'title' => 'Title', 'meta' => 'Meta description', 'h1' => 'H1', 'subheading' => 'H2 / H3',
        'url' => 'URL slug', 'first_100_words' => 'First 100 words', 'density' => 'Body density',
        'image_alt' => 'Image alt text', 'internal_anchor' => 'Internal link anchors',
    ];

    public const FIXES = [
        'title' => 'Put the phrase in the title, near the start.',
        'meta' => 'Use the phrase once in the meta description.',
        'h1' => 'Use the phrase (or a close variant) in the H1.',
        'subheading' => 'Add an H2 or H3 that includes the phrase.',
        'url' => 'If the page is new, use the phrase in the URL slug. Do not change live URLs without a 301 redirect.',
        'first_100_words' => 'Mention the phrase in the first paragraph.',
        'density' => 'Use the phrase naturally a few more times in the body (aim for 0.5–3%).',
        'image_alt' => 'Describe at least one relevant image with the phrase in its alt text.',
        'internal_anchor' => 'Link to this page from 2–3 related pages using the phrase as anchor text.',
    ];

    public function latestCrawl(Project $project): ?Crawl
    {
        return $project->crawls()->where('status', 'done')->latest('id')->first();
    }

    /** Usage rows for the crawl, grouped by keyword id. @return Collection<int, Collection<int, KeywordPageUsage>> */
    public function usageByKeyword(Project $project, ?Crawl $crawl): Collection
    {
        if (! $crawl) {
            return collect();
        }

        return KeywordPageUsage::query()
            ->whereIn('keyword_id', $project->keywords()->select('id'))
            ->whereIn('page_id', $crawl->pages()->select('id'))
            ->get()
            ->groupBy('keyword_id');
    }

    /** @return array{tracked:int,well_placed:int,needs_fixes:int,cannibalised:int,orphans:int,analysed:bool} */
    public function summary(Project $project): array
    {
        $keywords = $project->keywords()->get();
        $crawl = $this->latestCrawl($project);
        $usage = $this->usageByKeyword($project, $crawl);

        $out = ['tracked' => $keywords->count(), 'well_placed' => 0, 'needs_fixes' => 0, 'cannibalised' => 0, 'orphans' => 0, 'analysed' => $crawl !== null];
        foreach ($keywords as $keyword) {
            $rows = $usage->get($keyword->id, collect());
            $best = $this->bestScore($rows);
            if (! $keyword->target_url) {
                $out['orphans']++;
            }
            if ($best !== null && $best >= self::WELL_PLACED) {
                $out['well_placed']++;
            } elseif ($crawl) {
                $out['needs_fixes']++;
            }
            if ($rows->where('score', '>=', self::COMPETING)->count() >= 2) {
                $out['cannibalised']++;
            }
        }

        return $out;
    }

    /**
     * Columns for the heatmap: target pages first, then the pages that match most keywords.
     *
     * @return Collection<int, Page>
     */
    public function columns(Collection $usage, Collection $keywords, ?Crawl $crawl): Collection
    {
        if (! $crawl || $usage->isEmpty()) {
            return collect();
        }

        $all = $usage->flatten(1);
        $targets = $all->where('is_target', true)->pluck('page_id');
        $popular = $all->where('score', '>=', 40)->countBy('page_id')->sortDesc()->keys();
        $ids = $targets->merge($popular)->unique()->take(self::MAX_COLUMNS)->values();

        $pages = Page::whereIn('id', $ids)->get(['id', 'url', 'url_hash', 'title'])->keyBy('id');

        return $ids->map(fn ($id) => $pages->get($id))->filter()->values();
    }

    public function bestScore(Collection $rows): ?int
    {
        if ($rows->isEmpty()) {
            return null;
        }
        $target = $rows->firstWhere('is_target', true);

        return (int) ($target ? $target->score : $rows->max('score'));
    }

    /** Keywords where 2+ pages score 50+, with the competing pages. */
    public function cannibalised(Collection $keywords, Collection $usage): Collection
    {
        return $keywords->map(function (Keyword $keyword) use ($usage) {
            $rows = $usage->get($keyword->id, collect())->where('score', '>=', self::COMPETING)->sortByDesc('score');

            return $rows->count() >= 2 ? ['keyword' => $keyword, 'rows' => $rows->values()] : null;
        })->filter()->values();
    }

    /** Readable status for each placement check. @return list<array{key:string,label:string,state:string,detail:string}> */
    public function checkRows(KeywordPageUsage $usage, Page $page): array
    {
        $checks = $usage->checks ?? [];
        $rows = [];
        foreach (self::CHECK_LABELS as $key => $label) {
            $pass = (bool) ($checks[$key] ?? false);
            $detail = $pass ? 'yes' : 'not used';
            $state = $pass ? 'y' : 'n';

            if ($key === 'density') {
                $density = (float) ($checks['density_percent'] ?? 0);
                $detail = $density.'%'.($pass ? '' : ($density > KeywordUsageAnalyzer::DENSITY_MAX ? ' (too high)' : ' (low)'));
                if (! $pass && $density > 0) {
                    $state = 'h';
                }
            } elseif ($key === 'url') {
                $detail = parse_url($page->url, PHP_URL_PATH) ?: '/';
            } elseif ($key === 'image_alt') {
                $images = count($page->images ?? []);
                $detail = $pass ? 'yes' : ($images ? "not in {$images} image(s)" : 'no images');
            }

            $rows[] = compact('key', 'label', 'state', 'detail');
        }

        return $rows;
    }

    /** @return list<string> */
    public function fixes(KeywordPageUsage $usage): array
    {
        $checks = $usage->checks ?? [];

        return collect(self::FIXES)->filter(fn ($fix, $key) => ! ($checks[$key] ?? false))->values()->all();
    }
}

<?php

namespace App\Modules\Crawler\Http;

use App\Http\Controllers\Controller;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Models\Issue;
use App\Modules\Crawler\Models\Link;
use App\Modules\Crawler\Models\Page;
use App\Modules\Crawler\Services\CrawlStats;
use App\Modules\Keywords\Models\KeywordPageUsage;
use App\Modules\Projects\Http\ProjectController;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExplorerController extends Controller
{
    /** Quick filters: label + how to apply them. Issue-code filters match pages carrying that issue. */
    public const FILTERS = [
        'all' => 'All',
        'redirects' => 'Redirects',
        'broken' => 'Broken',
        'noindex' => 'Non-indexable',
        'h1_missing' => 'Missing H1',
        'title_long' => 'Title too long',
        'description_missing' => 'No meta description',
        'thin_content' => 'Thin content',
        'slow_response' => 'Slow',
    ];

    public const SORTS = ['url', 'status_code', 'word_count', 'depth', 'inlinks', 'load_ms', 'seo_score'];

    public function __construct(private CrawlStats $stats) {}

    public function index(Request $request, Project $project): View
    {
        ProjectController::authorizeProject($request, $project);
        $crawl = $this->crawlFor($request, $project);

        $filter = array_key_exists($request->query('f'), self::FILTERS) ? $request->query('f') : 'all';
        $search = trim((string) $request->query('q', ''));
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'depth';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $pages = null;
        $counts = [];
        $selected = null;
        if ($crawl) {
            foreach (array_keys(self::FILTERS) as $key) {
                $counts[$key] = $this->applyFilter($crawl->pages(), $key, $crawl)->count();
            }

            $query = $this->applyFilter($crawl->pages(), $filter, $crawl)
                ->select(['id', 'crawl_id', 'url', 'status_code', 'indexable', 'title', 'h1', 'word_count', 'depth', 'inlinks', 'outlinks', 'load_ms', 'seo_score', 'ai_score'])
                ->withCount('issues');
            if ($search !== '') {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q->where('url', 'like', $like)->orWhere('title', 'like', $like));
            }
            $pages = $query->orderBy($sort, $dir)->orderBy('id')->paginate(50)->withQueryString();

            if ($request->filled('p')) {
                $selected = $crawl->pages()->whereKey((int) $request->query('p'))->first();
            }
        }

        return view('crawler.explorer', [
            'project' => $project,
            'crawl' => $crawl,
            'crawls' => $project->crawls()->where('status', 'done')->latest('id')->limit(10)->get(['id', 'finished_at', 'pages_crawled']),
            'pages' => $pages,
            'filter' => $filter,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'counts' => $counts,
            'status' => $crawl ? $this->stats->statusCodes($crawl) : null,
            'times' => $crawl ? $this->stats->responseTimes($crawl) : null,
            'words' => $crawl ? $this->stats->wordCounts($crawl) : null,
            'changes' => $crawl ? $this->stats->changes($crawl) : null,
            'selected' => $selected,
            'detail' => $selected ? $this->pageDetail($crawl, $selected) : null,
        ]);
    }

    public function issues(Request $request, Project $project): View
    {
        ProjectController::authorizeProject($request, $project);
        $crawl = $this->crawlFor($request, $project);

        $severity = in_array($request->query('severity'), ['critical', 'warning', 'notice'], true) ? $request->query('severity') : null;
        $area = in_array($request->query('area'), ['seo', 'ai', 'speed'], true) ? $request->query('area') : null;

        $groups = $crawl ? $this->stats->issueGroups($crawl, $severity, $area) : [];
        $code = $request->query('code') ?: ($groups[0]['code'] ?? null);
        $current = collect($groups)->firstWhere('code', $code)
            ?? ($crawl && $code ? collect($this->stats->issueGroups($crawl))->firstWhere('code', $code) : null);

        $affected = collect();
        $siteMessages = collect();
        $broken = [];
        if ($crawl && $current) {
            $affected = Issue::where('crawl_id', $crawl->id)->where('code', $current['code'])->whereNotNull('page_id')
                ->with('page:id,url,status_code')->limit(100)->get();
            $siteMessages = Issue::where('crawl_id', $crawl->id)->where('code', $current['code'])->whereNull('page_id')->pluck('message');
            if ($current['code'] === 'broken_links') {
                $broken = $this->stats->brokenTargets($crawl);
            }
        }

        return view('crawler.issues', [
            'project' => $project,
            'crawl' => $crawl,
            'groups' => $groups,
            'all' => $crawl ? $this->stats->severityCounts($crawl) : null,
            'severity' => $severity,
            'area' => $area,
            'current' => $current,
            'guide' => $current ? (config('issue_guides.'.$current['code']) ?? null) : null,
            'affected' => $affected,
            'siteMessages' => $siteMessages,
            'broken' => $broken,
        ]);
    }

    /** ?crawl= picks an older finished crawl; default is the latest finished one, else the running one. */
    private function crawlFor(Request $request, Project $project): ?Crawl
    {
        if ($request->filled('crawl')) {
            return $project->crawls()->whereKey((int) $request->query('crawl'))->first();
        }

        return ProjectController::latestFinished($project) ?? $project->latestCrawl;
    }

    private function applyFilter(HasMany|Builder $query, string $filter, Crawl $crawl): HasMany|Builder
    {
        return match ($filter) {
            'all' => $query,
            'redirects' => $query->whereBetween('status_code', [300, 399]),
            'broken' => $query->where('status_code', '>=', 400),
            'noindex' => $query->where('indexable', false)->whereBetween('status_code', [200, 299]),
            default => $query->whereHas('issues', fn ($q) => $q->where('code', $filter)),
        };
    }

    private function pageDetail(Crawl $crawl, Page $page): array
    {
        $inlinks = Link::where('crawl_id', $crawl->id)->where('to_hash', $page->url_hash)->where('internal', true)
            ->limit(200)->get(['from_hash', 'anchor']);
        $sources = Page::where('crawl_id', $crawl->id)->whereIn('url_hash', $inlinks->pluck('from_hash')->unique()->take(10))
            ->pluck('url');

        return [
            'issues' => $page->issues()->get()->sortBy(fn ($i) => CrawlStats::SEVERITY_ORDER[$i->severity] ?? 9)->values(),
            'keywords' => KeywordPageUsage::where('page_id', $page->id)->with('keyword:id,keyword')->orderByDesc('score')->limit(12)->get(),
            'sources' => $sources,
            'anchors' => $inlinks->pluck('anchor')->filter()->countBy()->sortDesc()->take(6),
            'outlinks' => Link::where('crawl_id', $crawl->id)->where('from_hash', $page->url_hash)->count(),
            'broken_out' => Link::where('crawl_id', $crawl->id)->where('from_hash', $page->url_hash)->where('status_code', '>=', 400)->get(['to_url', 'status_code']),
        ];
    }
}

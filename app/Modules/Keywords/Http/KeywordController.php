<?php

namespace App\Modules\Keywords\Http;

use App\Http\Controllers\Controller;
use App\Modules\Crawler\Models\Page;
use App\Modules\Keywords\Jobs\AnalyzeKeywordUsage;
use App\Modules\Keywords\Models\Keyword;
use App\Modules\Keywords\Models\KeywordPageUsage;
use App\Modules\Keywords\Services\KeywordInsights;
use App\Modules\Projects\Http\ProjectController;
use App\Modules\Projects\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KeywordController extends Controller
{
    public const MAX_KEYWORDS = 500;

    public function __construct(private KeywordInsights $insights) {}

    public function index(Request $request, Project $project): View
    {
        ProjectController::authorizeProject($request, $project);

        $keywords = $project->keywords()->orderBy('keyword')->get();
        $crawl = $this->insights->latestCrawl($project);
        $usage = $this->insights->usageByKeyword($project, $crawl);
        $columns = $this->insights->columns($usage, $keywords, $crawl);

        // Selected cell: ?k=keyword&p=page, else the first keyword's target (or best) page.
        $selKeyword = $keywords->firstWhere('id', (int) $request->query('k')) ?? $keywords->first();
        $selUsage = null;
        if ($selKeyword) {
            $rows = $usage->get($selKeyword->id, collect());
            $selUsage = $request->filled('p')
                ? $rows->firstWhere('page_id', (int) $request->query('p'))
                : ($rows->firstWhere('is_target', true) ?? $rows->sortByDesc('score')->first());
        }
        $selPage = $selUsage ? Page::find($selUsage->page_id) : null;
        $cannibalised = $this->insights->cannibalised($keywords, $usage);
        $pageUrls = Page::whereIn('id', $cannibalised->flatMap(fn ($c) => $c['rows']->pluck('page_id'))->unique())->pluck('url', 'id');

        return view('keywords.index', [
            'project' => $project,
            'crawl' => $crawl,
            'keywords' => $keywords,
            'usage' => $usage,
            'columns' => $columns,
            'summary' => $this->insights->summary($project),
            'cannibalised' => $cannibalised,
            'pageUrls' => $pageUrls,
            'orphans' => $keywords->whereNull('target_url')->values(),
            'selKeyword' => $selKeyword,
            'selUsage' => $selUsage,
            'selPage' => $selPage,
            'checkRows' => $selUsage && $selPage ? $this->insights->checkRows($selUsage, $selPage) : [],
            'fixes' => $selUsage ? $this->insights->fixes($selUsage) : [],
            'insights' => $this->insights,
        ]);
    }

    /**
     * Add keywords, one per line. Optional target page after a comma, tab or "|":
     *   courier service sri lanka, https://parcel.lk/
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        ProjectController::authorizeProject($request, $project);
        $request->validate(['keywords' => ['required', 'string', 'max:50000']]);

        $existing = $project->keywords()->count();
        $added = 0;
        foreach (preg_split('/\R/', $request->input('keywords')) as $line) {
            [$phrase, $target] = array_pad(preg_split('/\s*[,\t|]\s*/', trim($line), 2), 2, null);
            $phrase = Str::of((string) $phrase)->squish()->lower()->limit(190, '')->value();
            if ($phrase === '' || $existing + $added >= self::MAX_KEYWORDS) {
                continue;
            }

            $keyword = Keyword::firstOrNew(['project_id' => $project->id, 'keyword' => $phrase]);
            if (! $keyword->exists) {
                $keyword->source = 'manual';
                $added++;
            }
            if ($target) {
                $keyword->target_url = $this->absoluteUrl($project, $target);
            }
            $keyword->save();
        }

        $this->reanalyse($project);

        return redirect()->route('keywords.index', $project)
            ->with('status', $added ? "Added {$added} keyword(s). Placement checks update within a minute or two." : 'No new keywords added.');
    }

    public function update(Request $request, Project $project, Keyword $keyword): RedirectResponse
    {
        ProjectController::authorizeProject($request, $project);
        abort_unless($keyword->project_id === $project->id, 404);
        $input = $request->validate(['target_url' => ['nullable', 'string', 'max:2048']]);

        $keyword->update(['target_url' => filled($input['target_url'] ?? null) ? $this->absoluteUrl($project, $input['target_url']) : null]);
        $this->reanalyse($project);

        return back()->with('status', "Target page updated for “{$keyword->keyword}”.");
    }

    public function destroy(Request $request, Project $project, Keyword $keyword): RedirectResponse
    {
        ProjectController::authorizeProject($request, $project);
        abort_unless($keyword->project_id === $project->id, 404);
        $keyword->delete();

        return redirect()->route('keywords.index', $project)->with('status', "Removed “{$keyword->keyword}”.");
    }

    public function analyse(Request $request, Project $project): RedirectResponse
    {
        ProjectController::authorizeProject($request, $project);

        return back()->with('status', $this->reanalyse($project)
            ? 'Re-checking keyword usage on the latest crawl.'
            : 'Run a crawl first, then keyword usage is checked automatically.');
    }

    private function reanalyse(Project $project): bool
    {
        $crawl = $this->insights->latestCrawl($project);
        if (! $crawl) {
            return false;
        }
        // Mapping can move the target page, so start from a clean slate for this crawl.
        KeywordPageUsage::whereIn('keyword_id', $project->keywords()->select('id'))
            ->whereIn('page_id', $crawl->pages()->select('id'))
            ->delete();
        AnalyzeKeywordUsage::dispatch($crawl->id);

        return true;
    }

    /** Accepts "/rates/", "rates" or a full URL on the project's site. */
    private function absoluteUrl(Project $project, string $value): string
    {
        $value = trim($value);
        if (preg_match('#^https?://#i', $value)) {
            return mb_substr($value, 0, 2048);
        }

        return rtrim($project->url, '/').'/'.ltrim($value, '/');
    }
}

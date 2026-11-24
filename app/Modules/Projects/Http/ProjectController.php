<?php

namespace App\Modules\Projects\Http;

use App\Http\Controllers\Controller;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Services\CrawlStats;
use App\Modules\Keywords\Services\KeywordInsights;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ProjectController extends Controller
{
    public const COUNTRIES = [
        'LK' => 'Sri Lanka', 'IN' => 'India', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom',
        'US' => 'United States', 'AU' => 'Australia', 'CA' => 'Canada', 'SG' => 'Singapore', 'MV' => 'Maldives',
    ];

    public const LANGUAGES = ['en' => 'English', 'si' => 'Sinhala', 'ta' => 'Tamil'];

    public const PAGE_LIMITS = [100, 250, 500, 1000];

    public function __construct(private ProjectService $projects, private CrawlStats $stats) {}

    /** Projects list + the New Project form. */
    public function index(Request $request): View
    {
        $workspaceIds = $request->user()->workspaces()->pluck('workspaces.id');
        $projects = Project::whereIn('workspace_id', $workspaceIds)
            ->with(['latestCrawl', 'steps' => fn ($q) => $q->where('enabled', true)])
            ->latest()
            ->get();

        return view('projects.index', [
            'projects' => $projects,
            'countries' => self::COUNTRIES,
            'languages' => self::LANGUAGES,
            'pageLimits' => self::PAGE_LIMITS,
            'maxPages' => (int) config('crawler.max_pages', 1000),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace, 403, 'You are not a member of any workspace.');

        $input = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'country' => ['required', 'string', 'size:2'],
            'language' => ['required', 'string', 'max:5'],
            'niche' => ['nullable', 'string', 'max:120'],
            'competitors' => ['nullable', 'array', 'max:5'],
            'competitors.*' => ['nullable', 'string', 'max:255'],
            'max_pages' => ['required', 'integer', 'min:10', 'max:'.(int) config('crawler.max_pages', 1000)],
            'suggest_competitors' => ['nullable', 'boolean'],
            'respect_robots' => ['nullable', 'boolean'],
        ]);

        try {
            $project = $this->projects->create($workspace, $request->user(), [
                'url' => $input['url'],
                'country' => $input['country'],
                'language' => $input['language'],
                'niche' => $input['niche'] ?? null,
                'competitors' => array_values(array_filter($input['competitors'] ?? [], fn ($c) => trim((string) $c) !== '')),
                'settings' => [
                    'max_pages' => (int) $input['max_pages'],
                    'suggest_competitors' => $request->boolean('suggest_competitors'),
                    'respect_robots' => $request->boolean('respect_robots'),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['url' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)
            ->with('status', 'Project created. The first crawl has started.');
    }

    public function show(Request $request, Project $project, KeywordInsights $keywords): View
    {
        $this->authorizeProject($request, $project);
        $project->load(['steps' => fn ($q) => $q->where('enabled', true)]);
        $crawl = $project->latestCrawl;
        $finished = $this->latestFinished($project);
        $view = $finished ?? $crawl; // charts use the last finished crawl while a new one runs

        return view('projects.show', [
            'project' => $project,
            'crawl' => $crawl,
            'shown' => $view,
            'progress' => $crawl ? $this->stats->progress($crawl) : null,
            'status' => $view ? $this->stats->statusCodes($view) : null,
            'depths' => $view ? $this->stats->depths($view) : null,
            'indexability' => $view ? $this->stats->indexability($view) : null,
            'severity' => $view ? $this->stats->severityCounts($view) : null,
            'topIssues' => $view ? array_slice($this->stats->issueGroups($view), 0, 5) : [],
            'changes' => $finished ? $this->stats->changes($finished) : null,
            'keywordStats' => $keywords->summary($project),
            'siteCodes' => $view ? $view->issues()->whereNull('page_id')->pluck('code')->all() : [],
        ]);
    }

    public function status(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $crawl = $project->latestCrawl;

        return response()->json($crawl ? $this->stats->progress($crawl) : ['status' => 'none', 'running' => false]);
    }

    public function recrawl(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $running = $project->crawls()->whereIn('status', ['queued', 'running'])->exists();
        if ($running) {
            return back()->with('status', 'A crawl is already running.');
        }

        $this->projects->startCrawl($project);

        return redirect()->route('projects.show', $project)->with('status', 'Re-crawl started.');
    }

    public static function latestFinished(Project $project): ?Crawl
    {
        return $project->crawls()->where('status', 'done')->latest('id')->first();
    }

    public static function authorizeProject(Request $request, Project $project): void
    {
        abort_unless($request->user()->belongsToWorkspace($project->workspace_id), 403);
    }
}

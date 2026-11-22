<?php

namespace App\Modules\Projects\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Competitors\Models\Competitor;
use App\Modules\Crawler\Jobs\StartCrawl;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Plan\Services\PlanService;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectService
{
    public function __construct(private PlanService $plan) {}

    /**
     * Create a project from a submitted URL: plan steps, manual competitors, first crawl.
     *
     * @param  array{url:string, name?:string, country?:string, language?:string, niche?:string, competitors?:list<string>, settings?:array}  $input
     */
    public function create(Workspace $workspace, User $user, array $input): Project
    {
        $url = self::normalizeUrl($input['url']);
        $domain = self::domainOf($url);

        $project = DB::transaction(function () use ($workspace, $user, $input, $url, $domain) {
            $project = Project::create([
                'workspace_id' => $workspace->id,
                'name' => $input['name'] ?? $domain,
                'url' => $url,
                'domain' => $domain,
                'country' => strtoupper($input['country'] ?? 'LK'),
                'language' => $input['language'] ?? 'en',
                'niche' => $input['niche'] ?? null,
                'settings' => array_merge([
                    'max_pages' => $workspace->limit('pages_per_crawl', 500),
                    'render_js' => false,
                    'respect_robots' => true,
                    'suggest_competitors' => true,
                ], $input['settings'] ?? []),
                'created_by' => $user->id,
            ]);

            foreach (array_slice($input['competitors'] ?? [], 0, 5) as $competitor) {
                $competitorDomain = self::domainOf(self::normalizeUrl($competitor));
                if ($competitorDomain !== $domain) {
                    Competitor::firstOrCreate(
                        ['project_id' => $project->id, 'domain' => $competitorDomain],
                        ['source' => 'manual'],
                    );
                }
            }

            $this->plan->initialize($project);

            return $project;
        });

        $this->startCrawl($project);

        return $project;
    }

    public function startCrawl(Project $project): Crawl
    {
        $crawl = $project->crawls()->create([
            'status' => 'queued',
            'max_pages' => (int) $project->setting('max_pages', 500),
        ]);

        StartCrawl::dispatch($crawl->id);

        return $crawl;
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Not a valid website address: {$url}");
        }

        return rtrim($url, '/');
    }

    public static function domainOf(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return preg_replace('/^www\./', '', $host);
    }
}

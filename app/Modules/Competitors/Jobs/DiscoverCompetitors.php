<?php

namespace App\Modules\Competitors\Jobs;

use App\Contracts\KeywordDataProvider;
use App\Modules\AI\AiManager;
use App\Modules\Competitors\Models\Competitor;
use App\Modules\Plan\Models\ProjectStep;
use App\Modules\Projects\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Step 1 automation:
 *  1. ask the data provider which domains share the most keywords with the project
 *  2. store them next to any competitors the customer typed in
 *  3. ask the AI for plain-English gaps, based only on those numbers
 *  4. put the step into review
 */
class DiscoverCompetitors implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public int $projectId, public int $stepId) {}

    public function handle(KeywordDataProvider $data, AiManager $ai): void
    {
        $project = Project::findOrFail($this->projectId);
        $step = ProjectStep::findOrFail($this->stepId);

        if ($project->setting('suggest_competitors', true)) {
            foreach ($data->competitors($project->domain, $project->country, $project->language, 8) as $row) {
                if ($row['domain'] === $project->domain) {
                    continue;
                }
                Competitor::updateOrCreate(
                    ['project_id' => $project->id, 'domain' => $row['domain']],
                    [
                        'shared_keywords' => $row['shared_keywords'],
                        'organic_keywords' => $row['organic_keywords'],
                        'estimated_traffic' => $row['estimated_traffic'],
                        'metrics_at' => now(),
                    ],
                );
            }
        }

        $competitors = $project->competitors()->where('included', true)->orderByDesc('shared_keywords')->get();

        $insights = null;
        try {
            $table = $competitors->map(fn ($c) => sprintf(
                '%s | shared keywords %s | organic keywords %s | est. traffic %s | DA %s',
                $c->domain, $c->shared_keywords ?? '?', $c->organic_keywords ?? '?', $c->estimated_traffic ?? '?', $c->domain_authority ?? '?',
            ))->implode("\n");

            $insights = $ai->run('analysis', 'competitor_insights', $project,
                system: 'You are a senior SEO strategist. Use ONLY the numbers given. Never invent metrics. '
                    .'Write short, plain English for a business owner. Return 3 to 5 bullet points: where the site is behind and what to do first.',
                prompt: "Website: {$project->domain} ({$project->niche}, country {$project->country}).\nCompetitors:\n{$table}",
            )->text;
        } catch (Throwable $e) {
            Log::warning('Competitor insights failed', ['project' => $project->id, 'error' => $e->getMessage()]);
        }

        $step->update([
            'status' => ProjectStep::REVIEW,
            'data' => array_merge($step->data ?? [], [
                'insights' => $insights,
                'generated_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function failed(Throwable $e): void
    {
        ProjectStep::whereKey($this->stepId)->update(['status' => ProjectStep::REVIEW]);
        Log::error('DiscoverCompetitors failed', ['project' => $this->projectId, 'error' => $e->getMessage()]);
    }
}

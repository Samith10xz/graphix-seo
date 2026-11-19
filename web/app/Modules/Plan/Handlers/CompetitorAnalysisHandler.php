<?php

namespace App\Modules\Plan\Handlers;

use App\Contracts\PlanStepHandler;
use App\Modules\Competitors\Jobs\DiscoverCompetitors;
use App\Modules\Plan\Models\ProjectStep;

/**
 * Step 1: find real search competitors, pull their metrics, write AI insights.
 * Heavy work runs in DiscoverCompetitors; this only starts it.
 */
class CompetitorAnalysisHandler implements PlanStepHandler
{
    public function generate(ProjectStep $step): void
    {
        $step->update(['status' => ProjectStep::GENERATING]);
        // afterCommit: the project may still be inside its creation transaction
        DiscoverCompetitors::dispatch($step->project_id, $step->id)->afterCommit();
    }

    public function validate(ProjectStep $step): array
    {
        $included = $step->project->competitors()->where('included', true)->count();

        return $included === 0
            ? ['Keep at least one competitor before approving.']
            : [];
    }
}

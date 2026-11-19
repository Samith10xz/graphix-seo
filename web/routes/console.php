<?php

use App\Modules\Plan\Services\PlanService;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Add steps that were added to config/seo_plan.php to existing projects (disabled).
Artisan::command('plan:sync', function (PlanService $plan) {
    $total = 0;
    Project::query()->each(function (Project $project) use ($plan, &$total) {
        $total += $plan->sync($project);
    });
    $this->info("Added {$total} step(s).");
})->purpose('Sync SEO plan steps from config into existing projects');

Schedule::command('queue:prune-failed --hours=168')->daily();

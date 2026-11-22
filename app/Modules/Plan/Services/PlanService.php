<?php

namespace App\Modules\Plan\Services;

use App\Contracts\PlanStepHandler;
use App\Models\User;
use App\Modules\Plan\Handlers\ManualStepHandler;
use App\Modules\Plan\Models\ProjectStep;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Runs the step-by-step SEO plan: only the first unapproved enabled step is open;
 * approving it unlocks the next one and starts its auto-generation.
 */
class PlanService
{
    /** Copy the default steps from config into a new project. */
    public function initialize(Project $project): void
    {
        foreach (config('seo_plan.steps') as $i => $def) {
            ProjectStep::firstOrCreate(
                ['project_id' => $project->id, 'key' => $def['key']],
                ['name' => $def['name'], 'position' => ($i + 1) * 10, 'enabled' => $def['default'] ?? true, 'status' => ProjectStep::LOCKED],
            );
        }

        $this->openNext($project);
    }

    /** Add steps that were added to config after the project was created (disabled, so clients opt in). */
    public function sync(Project $project): int
    {
        $existing = $project->steps()->pluck('key')->all();
        $max = (int) $project->steps()->max('position');
        $added = 0;

        foreach (config('seo_plan.steps') as $def) {
            if (! in_array($def['key'], $existing, true)) {
                $max += 10;
                $project->steps()->create(['key' => $def['key'], 'name' => $def['name'], 'position' => $max, 'enabled' => false, 'status' => ProjectStep::LOCKED]);
                $added++;
            }
        }

        return $added;
    }

    /** Add a step only this client has (e.g. "Local SEO"), placed after $afterKey. */
    public function addCustomStep(Project $project, string $key, string $name, ?string $afterKey = null): ProjectStep
    {
        $after = $afterKey ? $project->steps()->where('key', $afterKey)->value('position') : $project->steps()->max('position');

        return DB::transaction(function () use ($project, $key, $name, $after) {
            $project->steps()->where('position', '>', (int) $after)->increment('position', 10);

            return $project->steps()->create([
                'key' => $key, 'name' => $name, 'position' => (int) $after + 10,
                'enabled' => true, 'custom' => true, 'status' => ProjectStep::LOCKED,
            ]);
        });
    }

    /** @return list<string> problems; empty when approved */
    public function approve(ProjectStep $step, User $user): array
    {
        if (! in_array($step->status, [ProjectStep::READY, ProjectStep::REVIEW], true)) {
            return ["This step is {$step->status} and cannot be approved now."];
        }

        $problems = $this->handlerFor($step)->validate($step);
        if ($problems !== []) {
            return $problems;
        }

        $step->update(['status' => ProjectStep::APPROVED, 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->openNext($step->project);

        return [];
    }

    /** Re-open an approved step for edits; later steps keep their state. */
    public function reopen(ProjectStep $step): void
    {
        $step->update(['status' => ProjectStep::REVIEW, 'approved_by' => null, 'approved_at' => null]);
    }

    /** Unlock the first enabled step that is still locked, if all before it are approved. */
    public function openNext(Project $project): ?ProjectStep
    {
        foreach ($project->steps()->where('enabled', true)->get() as $step) {
            if ($step->isApproved()) {
                continue;
            }
            if ($step->status !== ProjectStep::LOCKED) {
                return null; // a step is already open
            }

            $step->update(['status' => ProjectStep::READY]);
            $this->handlerFor($step)->generate($step);

            return $step;
        }

        return null; // plan complete
    }

    public function handlerFor(ProjectStep $step): PlanStepHandler
    {
        $class = $step->definition()['handler'] ?? ManualStepHandler::class;
        $handler = app($class);

        if (! $handler instanceof PlanStepHandler) {
            throw new RuntimeException("{$class} must implement PlanStepHandler");
        }

        return $handler;
    }
}

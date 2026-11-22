<?php

namespace App\Modules\Plan\Handlers;

use App\Contracts\PlanStepHandler;
use App\Modules\Plan\Models\ProjectStep;

/** Default for steps without automation yet: the team fills them in, then approves. */
class ManualStepHandler implements PlanStepHandler
{
    public function generate(ProjectStep $step): void
    {
        $step->update(['status' => ProjectStep::REVIEW, 'data' => $step->data ?? ['rows' => []]]);
    }

    public function validate(ProjectStep $step): array
    {
        return [];
    }
}

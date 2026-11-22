<?php

namespace App\Contracts;

use App\Modules\Plan\Models\ProjectStep;

interface PlanStepHandler
{
    /** Fill $step->data automatically (may dispatch jobs). Called when the step unlocks. */
    public function generate(ProjectStep $step): void;

    /**
     * Problems that block approval, in plain words. Empty array = ready to approve.
     *
     * @return list<string>
     */
    public function validate(ProjectStep $step): array;
}

<?php

namespace App\Modules\Plan\Http;

use App\Http\Controllers\Controller;
use App\Modules\Plan\Models\ProjectStep;
use App\Modules\Plan\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StepController extends Controller
{
    public function __construct(private PlanService $plan) {}

    public function approve(Request $request, ProjectStep $step): JsonResponse
    {
        $this->authorizeStep($request, $step);

        $problems = $this->plan->approve($step, $request->user());

        return $problems === []
            ? response()->json($step->project->steps()->get())
            : response()->json(['message' => 'Not ready to approve', 'problems' => $problems], 422);
    }

    public function update(Request $request, ProjectStep $step): JsonResponse
    {
        $this->authorizeStep($request, $step);

        $data = $request->validate([
            'data' => ['sometimes', 'array'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $step->update($data);

        return response()->json($step);
    }

    private function authorizeStep(Request $request, ProjectStep $step): void
    {
        $role = $request->user()->roleIn($step->project->workspace);
        abort_unless(in_array($role, ['owner', 'manager'], true), 403);
    }
}

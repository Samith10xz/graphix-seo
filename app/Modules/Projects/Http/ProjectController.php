<?php

namespace App\Modules\Projects\Http;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($request->user()->roleIn($workspace), 403);

        $input = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'name' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'language' => ['nullable', 'string', 'max:5'],
            'niche' => ['nullable', 'string', 'max:120'],
            'competitors' => ['nullable', 'array', 'max:5'],
            'competitors.*' => ['string', 'max:255'],
            'settings' => ['nullable', 'array'],
        ]);

        $project = $this->projects->create($workspace, $request->user(), $input);

        return response()->json($project->load('steps', 'competitors', 'latestCrawl'), 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()->roleIn($project->workspace), 403);

        return response()->json($project->load('steps', 'competitors', 'latestCrawl'));
    }

    public function recrawl(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()->roleIn($project->workspace), 403);

        return response()->json($this->projects->startCrawl($project), 202);
    }
}

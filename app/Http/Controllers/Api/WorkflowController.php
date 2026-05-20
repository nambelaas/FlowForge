<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use App\Models\Workflows;
use App\Models\WorkflowVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    public function index(Request $request)
    {
        $query = Workflows::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $pageSize = $request->get('per_page', 5);
        $currentPage = $request->get('current_page', 1);

        $data = $query->simplePaginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'dag_definition' => 'required|array',
            'dag_definition.steps' => 'required|array|min:1',
            'dag_definition.steps.*.id' => 'required|string',
            'dag_definition.steps.*.type' => 'required|in:HTTP,SCRIPT,DELAY,CONDITIONAL',
            'dag_definition.steps.*.action' => 'required_if:dag_definition.steps.*.type,HTTP,SCRIPT|string',
            'dag_definition.steps.*.duration' => 'required_if:dag_definition.steps.*.type,DELAY|integer|min:1',
            'dag_definition.steps.*.retry_logic' => 'array|nullable',
            'dag_definition.steps.*.retry_logic.max_retries' => 'required_with:dag_definition.steps.*.retry_logic|integer|min:0'
        ]);

        return DB::transaction(function () use ($validated) {
            $workflow = Workflows::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            WorkflowVersion::create([
                'workflows_id' => $workflow->id,
                'version_number' => 1,
                'dag_definition' => $validated['dag_definition'],
            ]);

            return response()->json([
                'message' => 'Workflow created successfully',
                'data' => $workflow->load('latestVersion')
            ], 201);
        });
    }

    public function update(Request $request, Workflows $workflow)
    {
        $validated = $request->validate([
            'dag_definition' => 'required|array',
        ]);

        return DB::transaction(function () use ($validated, $workflow) {
            $latestVersionNumber = $workflow->versions()->max('version_number') ?? 0;

            $newVersion = WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'version_number' => $latestVersionNumber + 1,
                'dag_definition' => $validated['dag_definition'],
            ]);

            return response()->json([
                'message' => "Workflow updated to version " . ($latestVersionNumber + 1),
                'data' => $newVersion
            ]);
        });
    }
}

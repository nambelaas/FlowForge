<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use Illuminate\Http\Request;

class WorkflowRunApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = WorkflowRun::query()->where('tenant_id', $user->tenant_id);

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('started_at_from') && $request->started_at_from != '') {
            $query->where('started_at', '>=', $request->started_at_from);
        }

        if ($request->has('started_at_to') && $request->started_at_to != '') {
            $query->where('started_at', '<=', $request->started_at_to);
        }

        if ($request->has('completed_at_from') && $request->completed_at_from != '') {
            $query->where('completed_at', '>=', $request->completed_at_from);
        }

        if ($request->has('completed_at_to') && $request->completed_at_to != '') {
            $query->where('completed_at', '<=', $request->completed_at_to);
        }

        $pageSize = $request->get('per_page', 5);
        $currentPage = $request->get('current_page', 1);

        $data = $query->simplePaginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json($data);
    }

    public function steps(Request $request, $runId)
    {
        if (!WorkflowRun::where('id', $runId)->exists()) {
            return response()->json(['message' => 'Workflow Run not found'], 404);
        }

        $query = StepRun::query()->where('workflow_run_id', $runId);

        if ($request->has('step_id') && $request->step_id != '') {
            $query->where('step_id', $request->step_id);
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type != '') {
            $query->where('type', $request->type);
        }

        if ($request->has('duration_min') && $request->duration_min != '') {
            $query->where('duration_ms', '>=', $request->duration_min);
        }

        if ($request->has('duration_max') && $request->duration_max != '') {
            $query->where('duration_ms', '<=', $request->duration_max);
        }

        if ($request->has('started_at_from') && $request->started_at_from != '') {
            $query->where('started_at', '>=', $request->started_at_from);
        }

        if ($request->has('started_at_to') && $request->started_at_to != '') {
            $query->where('started_at', '<=', $request->started_at_to);
        }

        if ($request->has('completed_at_from') && $request->completed_at_from != '') {
            $query->where('completed_at', '>=', $request->completed_at_from);
        }

        if ($request->has('completed_at_to') && $request->completed_at_to != '') {
            $query->where('completed_at', '<=', $request->completed_at_to);
        }

        $pageSize = $request->get('per_page', 5);
        $currentPage = $request->get('current_page', 1);

        $data = $query->simplePaginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json($data);
    }
}

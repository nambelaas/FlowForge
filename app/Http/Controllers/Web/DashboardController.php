<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use App\Models\Workflows;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;

        $workflowQuery = Workflows::where('tenant_id', $tenantId);

        if ($request->has('search') && $request->search != '') {
            $workflowQuery->where('name', 'like', '%' . $request->search . '%');
        }

        $paginatedWorkflows = $workflowQuery->paginate(3)->withQueryString();

        $latestRun = WorkflowRun::orderBy('started_at', 'desc')->first();

        $stepRuns = $latestRun ? StepRun::where('workflow_run_id', $latestRun->id)->get() : [];
        $oneDayAgo = Carbon::now()->subDay();

        $totalRuns24h = WorkflowRun::where('started_at', '>=', $oneDayAgo)->count();

        $successCount = WorkflowRun::where('status', 'SUCCESS')
            ->where('started_at', '>=', $oneDayAgo)
            ->count();

        $failureCount = WorkflowRun::where('status', 'FAILED')
            ->where('started_at', '>=', $oneDayAgo)
            ->count();

        $successRate = $totalRuns24h > 0 ? round(($successCount / $totalRuns24h) * 100) : 0;
        $failureRate = $totalRuns24h > 0 ? round(($failureCount / $totalRuns24h) * 100) : 0;

        $avgDuration = StepRun::where('status', 'SUCCESS')
            ->where('started_at', '>=', $oneDayAgo)
            ->avg('duration_ms') ?? 0;

        $healthMetrics = [
            'active_runs' => WorkflowRun::where('status', 'RUNNING')->count(),
            'success_rate' => $successRate,
            'failure_rate' => $failureRate,
            'avg_execution_time' => $avgDuration,
        ];

        return inertia('Workflow/Dashboard', [
            'workflowsData' => $paginatedWorkflows,
            'initialStepRuns' => $stepRuns,
            'tenantId' => $tenantId,
            'healthMetrics' => $healthMetrics
        ]);
    }
}

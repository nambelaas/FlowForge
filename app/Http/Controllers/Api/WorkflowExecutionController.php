<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StepRun;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\Workflows;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;

class WorkflowExecutionController extends Controller
{
    protected $engine;

    // Inject WorkflowEngine ke dalam controller
    public function __construct(WorkflowEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Memicu eksekusi alur kerja secara manual via API atau Webhook
     */
    public function trigger(Request $request, $id)
    {
        $workflow = Workflows::findOrFail($id);

        $latestVersion = $workflow->versions()->orderBy('version_number', 'desc')->first();

        if (!$latestVersion) {
            return response()->json([
                'message' => 'Gagal mengeksekusi: Alur kerja ini belum memiliki definisi langkah (DAG).'
            ], 422);
        }

        try {
            $tenantId = Auth::user()->tenant_id;
            $this->engine->execute($latestVersion, $tenantId);

            return response()->json([
                'message' => 'Workflow execution dispatched successfully',
                'workflow_id' => $workflow->id,
                'status' => 'RUNNING'
            ], 202); // 202 Accepted artinya proses sudah masuk antrean background

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memvalidasi struktur alur kerja.',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function stopRun($workflowId)
    {
        $workflow = Workflows::findOrFail($workflowId);

        $workflowVersion = $workflow->versions()->first();

        if (!$workflowVersion) {
            return response()->json(['message' => 'Version not found.'], 404);
        }

        $activeRun = WorkflowRun::where('workflow_version_id', $workflowVersion->id)
            ->where('status', 'RUNNING')
            ->orderBy('started_at', 'desc')
            ->first();

        if (!$activeRun) {
            return response()->json(['message' => 'No active running workflow found to stop.']);
        }

        $activeRun->update([
            'status' => 'FAILED',
            'completed_at' => now()
        ]);

        StepRun::where('workflow_run_id', $activeRun->id)
            ->whereIn('status', ['PENDING', 'RUNNING'])
            ->update([
                'status' => 'FAILED',
                'logs' => 'Execution terminated forcefully by the user via Dashboard Control.'
            ]);

        $latestStepRun = StepRun::where('workflow_run_id', $activeRun->id)->latest()->first();

        if ($latestStepRun) {
            event(new \App\Events\WorkflowStepUpdated($latestStepRun));
        }

        return response()->json([
            'message' => 'Workflow process terminated successfully.',
            'workflow_run_id' => $activeRun->id
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\Workflows;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
}

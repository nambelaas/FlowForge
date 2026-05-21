<?php

namespace App\Events;

use App\Models\StepRun;
use App\Models\WorkflowRun;
use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WorkflowStepUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $stepRun;
    public $latestMetrics;

    public $tenantId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        StepRun $stepRun,
        ?string $tenantId
    ) {
        $log = Log::channel('workflow');

        $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Send event for step run update');

        $this->stepRun = $stepRun->load('workflowRun');
        $this->tenantId = $tenantId;

        $oneDayAgo = Carbon::now()->subDay();
        $totalRuns24h = WorkflowRun::where('started_at', '>=', $oneDayAgo)->count();
        $activeRuns = WorkflowRun::where('status', 'RUNNING')->count();
        $successCount = WorkflowRun::where('status', 'SUCCESS')->where('started_at', '>=', $oneDayAgo)->count();
        $failureCount = WorkflowRun::where('status', 'FAILED')->where('started_at', '>=', $oneDayAgo)->count();

        $successRate = $totalRuns24h > 0 ? round(($successCount / $totalRuns24h) * 100) : 0;
        $failureRate = $totalRuns24h > 0 ? round(($failureCount / $totalRuns24h) * 100) : 0;

        $avgDuration = StepRun::where('status', 'SUCCESS')->where('started_at', '>=', $oneDayAgo)->avg('duration_ms') ?? 0;

        $this->latestMetrics = [
            'active_runs' => $activeRuns,
            'success_rate' => $successRate . '%',
            'failure_rate' => $failureRate . '%',
            'avg_execution_time' => round($avgDuration) . ' ms'
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workflows-' . $this->tenantId)];
    }

    public function broadcastAs(): string
    {
        return 'step.updated';
    }
}

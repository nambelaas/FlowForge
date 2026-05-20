<?php

namespace App\Events;

use App\Models\StepRun;
use App\Models\WorkflowRun;
use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowStepUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $stepRun;
    public $latestMetrics;

    /**
     * Create a new event instance.
     */
    public function __construct(StepRun $stepRun)
    {
        $this->stepRun = $stepRun->load('workflowRun');

        // HITUNG ULANG METRIK SECARA LIVE UNTUK DISUNTIKKAN KE WEBSOCKET
        $oneDayAgo = Carbon::now()->subDay();
        $totalRuns24h = WorkflowRun::where('created_at', '>=', $oneDayAgo)->count();
        $activeRuns = WorkflowRun::where('status', 'RUNNING')->count();
        $successCount = WorkflowRun::where('status', 'SUCCESS')->where('created_at', '>=', $oneDayAgo)->count();
        $failureCount = WorkflowRun::where('status', 'FAILED')->where('created_at', '>=', $oneDayAgo)->count();

        $successRate = $totalRuns24h > 0 ? round(($successCount / $totalRuns24h) * 100) : 0;
        $failureRate = $totalRuns24h > 0 ? round(($failureCount / $totalRuns24h) * 100) : 0;

        $avgDuration = StepRun::where('status', 'SUCCESS')->where('created_at', '>=', $oneDayAgo)->avg('duration_ms') ?? 0;

        $this->latestMetrics = [
            'active_runs' => $activeRuns,
            'success_rate' => $successRate . '%',
            'failure_rate' => $failureRate . '%',
            'avg_execution_time' => round($avgDuration) . ' ms'
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('workflows-public')];
    }

    public function broadcastAs(): string
    {
        return 'step.updated';
    }
}

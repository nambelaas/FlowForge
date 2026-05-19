<?php

namespace App\Events;

use App\Models\StepRun;
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

    /**
     * Create a new event instance.
     */
    public function __construct(StepRun $stepRun)
    {
        $this->stepRun = $stepRun;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $tenantId = $this->stepRun->workflowRun->tenant_id;

        return [
            new PrivateChannel("tenant.{$tenantId}.workflows")
        ];
    }

    public function broadcastAs(): string
    {
        return 'step.updated';
    }
}

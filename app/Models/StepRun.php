<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepRun extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'workflow_run_id',
        'step_id',
        'type',
        'status',
        'logs',
        'ai_analysis',
        'duration_ms',
        'started_at',
        'completed_at',
    ];

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StepRun extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'workflow_run_id',
        'step_id',
        'type',
        'status',
        'logs',
        'duration_ms',
        'started_at',
        'completed_at',
    ];
}

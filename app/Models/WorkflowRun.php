<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowRun extends Model
{
    protected $fillable = [
        'workflow_version_id',
        'tenant_id',
        'status',
        'started_at',
        'completed_at',
    ];
}

<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRun extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'workflow_version_id',
        'tenant_id',
        'status',
        'started_at',
        'completed_at',
    ];

    protected static function booted()
    {
        static::initializeTenant(new static);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }
}

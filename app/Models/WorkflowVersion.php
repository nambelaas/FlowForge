<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowVersion extends Model
{

    public $timestamps = false;

    protected $fillable = [
        'workflows_id',
        'version_number',
        'dag_definition',
    ];

    protected $casts = [
        'dag_definition' => 'array', // Memastikan JSON otomatis dikonversi ke array PHP
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflows::class);
    }

    public function workflowRuns(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }
}

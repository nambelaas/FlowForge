<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowVersion extends Model
{
    protected $fillable = [
        'workflow_id',
        'version_number',
        'definition',
    ];
}

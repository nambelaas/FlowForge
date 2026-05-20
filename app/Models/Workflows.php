<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workflows extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
    ];

    protected static function booted()
    {
        static::initializeTenant(new static);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class)->latestOfMany('version_number');
    }
}

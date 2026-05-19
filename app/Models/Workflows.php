<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workflows extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
    ];
}

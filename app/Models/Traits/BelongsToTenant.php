<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[ScopedBy([TenantScope::class])]
trait BelongsToTenant
{
    public static function initializeTenant(Model $model): void
    {
        $model::addGlobalScope(new TenantScope);

        $model::creating(function (Model $item) {
            if (Auth::check() && ! $item->getAttribute('tenant_id')) {
                $item->setAttribute('tenant_id', Auth::user()->tenant_id);
            }
        });
    }
}

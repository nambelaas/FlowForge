<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}.workflows', function ($user, $tenantId) {
    return (int) $user->tenant_id === $tenantId;
});

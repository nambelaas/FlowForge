<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('workflows-{$tenantId}', function ($user, $tenantId) {
    return (string) $user->tenant_id === (string) $tenantId;
});

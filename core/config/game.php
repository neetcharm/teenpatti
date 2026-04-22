<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Session Idle Timeout
    |--------------------------------------------------------------------------
    |
    | Tenant-backed game sessions are auto-closed when the player stays
    | inactive for this many minutes.
    |
    */
    'tenant_session_idle_timeout_minutes' => (int) env('TENANT_SESSION_IDLE_TIMEOUT_MINUTES', 5),
];


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

    /*
    |--------------------------------------------------------------------------
    | Wallet Win Credit Async Mode
    |--------------------------------------------------------------------------
    |
    | Keep this false for real-time win settlement. When true, win credits are
    | queued and depend on a running queue worker.
    |
    */
    'wallet_win_credit_async' => filter_var(env('WALLET_WIN_CREDIT_ASYNC', false), FILTER_VALIDATE_BOOLEAN),
];

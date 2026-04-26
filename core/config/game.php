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

    /*
    |----------------------------------------------------------------------
    | Tenant Legacy API Signature Mode
    |----------------------------------------------------------------------
    |
    | Keep disabled so tenant API requests must include X-Timestamp and
    | X-Nonce with the canonical HMAC signature. Enable temporarily only
    | while migrating an old client that still signs the raw body only.
    |
    */
    'tenant_allow_legacy_signatures' => filter_var(env('TENANT_ALLOW_LEGACY_SIGNATURES', false), FILTER_VALIDATE_BOOLEAN),
];

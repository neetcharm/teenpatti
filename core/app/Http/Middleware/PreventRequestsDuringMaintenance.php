<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array<int, string>
     */
    protected $except = [
        'up',
        'play',
        'tp/*',
        'launch/*',
        'api/v1/session/create',
        'api/v1/session/end',
        'api/v1/session/close',
        'api/v1/game/session',
        'api/v1/game/*/start',
        'api/v1/game/*/play',
        'user/play/teen-patti/global/sync*',
        'user/play/teen-patti/history*',
        'user/play/tenant/wallet/refresh',
        'user/play/invest/*',
        'user/play/end/*',
    ];
}

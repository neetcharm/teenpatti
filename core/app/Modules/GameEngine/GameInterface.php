<?php

namespace App\Modules\GameEngine;

use App\Models\TenantSession;

interface GameInterface
{
    /**
     * Start/Initialize a game for the given session.
     * @param TenantSession $session
     * @param array $params
     * @return array
     */
    public function start(TenantSession $session, array $params): array;

    /**
     * Execute a bet or play round.
     * @param TenantSession $session
     * @param array $params
     * @return array
     */
    public function play(TenantSession $session, array $params): array;

    /**
     * Complete a game/calculate final result.
     * @param TenantSession $session
     * @param string $gameLogId
     * @return array
     */
    public function result(TenantSession $session, string $gameLogId): array;
}

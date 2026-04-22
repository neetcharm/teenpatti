<?php

namespace App\Modules\GameEngine;

use App\Models\TenantSession;
use App\Services\TeenPattiGlobalManager;
use App\Modules\WalletBridge\WalletBridgeService;

class TeenPattiGame extends BaseModularGame
{
    public function __construct(WalletBridgeService $wallet)
    {
        parent::__construct($wallet);
    }

    /**
     * Entry point to get current game state (sync).
     */
    public function start(TenantSession $session, array $params): array
    {
        $manager = $this->managerForSession($session);
        $sync = $manager->getSync((int) $session->internal_user_id);
        
        return [
            'status' => 'success',
            'state'  => $sync,
            'balance' => $session->balance_cache
        ];
    }

    /**
     * Place a bet in the global round.
     */
    public function play(TenantSession $session, array $params): array
    {
        $manager = $this->managerForSession($session);
        $placeholder = $params['choose'] ?? '';
        $amount      = (float) ($params['invest'] ?? 0);

        // Delegate wallet debit/rollback and round write consistency to GlobalManager.
        $betResult = $manager->placeBet((int) $session->internal_user_id, $placeholder, $amount);

        if (isset($betResult['error'])) {
            return ['status' => 'error', 'message' => $betResult['error']];
        }

        return [
            'status'      => 'success',
            'balance'     => $betResult['balance'] ?? $session->balance_cache,
            'my_bets'     => $betResult['my_bets'] ?? [],
            'totals'      => $betResult['totals'] ?? [],
        ];
    }

    private function managerForSession(TenantSession $session): TeenPattiGlobalManager
    {
        return new TeenPattiGlobalManager(demoMode: false, tenantId: (int) $session->tenant_id);
    }
}

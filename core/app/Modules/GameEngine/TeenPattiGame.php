<?php

namespace App\Modules\GameEngine;

use App\Models\TenantSession;
use App\Services\TeenPattiGlobalManager;
use App\Modules\WalletBridge\WalletBridgeService;

class TeenPattiGame extends BaseModularGame
{
    protected TeenPattiGlobalManager $manager;

    public function __construct(WalletBridgeService $wallet)
    {
        parent::__construct($wallet);
        // We reuse the existing Manager logic for the rounds/phases but bridge the wallet
        $this->manager = new TeenPattiGlobalManager(demoMode: false);
    }

    /**
     * Entry point to get current game state (sync).
     */
    public function start(TenantSession $session, array $params): array
    {
        $sync = $this->manager->getSync((int) $session->internal_user_id);
        
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
        $placeholder = $params['choose'] ?? '';
        $amount      = (float) ($params['invest'] ?? 0);

        // 1. Validate round phase
        if ($this->manager->currentPhase() !== 'betting') {
            return ['status' => 'error', 'message' => 'Betting is closed!'];
        }

        // 2. Debit external wallet BEFORE recording local state
        $roundId = (string) $this->manager->currentRound();
        $txnId   = 'tp_bet_' . uniqid();
        
        $newBalance = $this->debit($session, $amount, $roundId, $txnId);

        if ($newBalance === false) {
            return ['status' => 'error', 'message' => 'Insufficient funds in external wallet.'];
        }

        // 3. Update local session cache
        $session->update(['balance_cache' => $newBalance]);

        // 4. Register bet in the Global Manager (which uses Cache for real-time)
        // Note: we might need to modify GlobalManager to skip internal balance checks if called from SaaS
        // For now, we assume if we reached here, the debit is successful.
        $betResult = $this->manager->placeBet((int) $session->internal_user_id, $placeholder, $amount);

        if (isset($betResult['error'])) {
            // Rollback if needed (implemented in WalletBridgeService)
            // For now return error
            return ['status' => 'error', 'message' => $betResult['error']];
        }

        return [
            'status'      => 'success',
            'balance'     => $newBalance,
            'my_bets'     => $betResult['my_bets'] ?? [],
            'totals'      => $betResult['totals'] ?? [],
        ];
    }
}

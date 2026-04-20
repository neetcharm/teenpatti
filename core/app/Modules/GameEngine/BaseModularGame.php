<?php

namespace App\Modules\GameEngine;

use App\Models\TenantSession;
use App\Modules\WalletBridge\WalletBridgeService;
use Illuminate\Support\Facades\Log;

abstract class BaseModularGame implements GameInterface
{
    protected WalletBridgeService $wallet;

    public function __construct(WalletBridgeService $wallet)
    {
        $this->wallet = $wallet;
    }

    /**
     * Helper to perform a debit (bet) on the external wallet.
     * 
     * @param TenantSession $session
     * @param float $amount
     * @param string $roundId
     * @param string|null $txnId
     * @return float|false New balance or false on failure
     */
    protected function debit(TenantSession $session, float $amount, string $roundId, ?string $txnId = null)
    {
        $result = $this->wallet->debit($session, $amount, $roundId, $txnId);
        
        if ($result === false) {
            Log::warning("SaaS_Game_Debit_Failed: Round {$roundId} for session {$session->session_token}");
        }
        
        return $result;
    }

    /**
     * Helper to perform a credit (win) on the external wallet.
     */
    protected function credit(TenantSession $session, float $amount, string $roundId, ?string $txnId = null)
    {
        return $this->wallet->credit($session, $amount, $roundId, $txnId);
    }

    /**
     * Generic result call. Can be overridden if needed.
     */
    public function result(TenantSession $session, string $gameLogId): array
    {
        return [
            'status' => 'success',
            'message' => 'Result handled.'
        ];
    }
}

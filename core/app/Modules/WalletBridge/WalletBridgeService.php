<?php

namespace App\Modules\WalletBridge;

use App\Jobs\ProcessWalletWin;
use App\Models\TenantSession;
use App\Services\TenantWebhookService;
use Illuminate\Support\Facades\Log;

class WalletBridgeService
{
    /**
     * Debit from the tenant-managed wallet.
     *
     * @return float|false The latest balance, or false on failure.
     */
    public function debit(TenantSession $session, float $amount, string $roundId, ?string $transactionId = null)
    {
        $tenant = $session->tenant;
        if (!$tenant) {
            Log::error('Wallet Bridge Error: tenant not found for debit', ['session_id' => $session->id]);
            return false;
        }

        $result = (new TenantWebhookService($tenant))->debit(
            $session,
            $amount,
            $roundId,
            $transactionId
        );

        return $result['ok'] ? (float) $result['balance'] : false;
    }

    /**
     * Credit to the tenant-managed wallet.
     *
     * @return float|bool The latest balance, true when queued async, or false on failure.
     */
    public function credit(
        TenantSession $session,
        float $amount,
        string $roundId,
        ?string $transactionId = null,
        ?string $description = null,
        bool $async = false
    ) {
        if ($async) {
            ProcessWalletWin::dispatch($session, $amount, $roundId, $transactionId, $description);
            return true;
        }

        $tenant = $session->tenant;
        if (!$tenant) {
            Log::error('Wallet Bridge Error: tenant not found for credit', ['session_id' => $session->id]);
            return false;
        }

        $result = (new TenantWebhookService($tenant))->credit(
            $session,
            $amount,
            $roundId,
            $transactionId
        );

        return $result['ok'] ? (float) $result['balance'] : false;
    }
}

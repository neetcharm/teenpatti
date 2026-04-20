<?php

namespace App\Jobs;

use App\Models\TenantSession;
use App\Modules\WalletBridge\WalletBridgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWalletWin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $session;
    protected $amount;
    protected $roundId;
    protected $transactionId;
    protected $description;

    /**
     * Create a new job instance.
     */
    public function __construct(
        TenantSession $session,
        float $amount,
        string $roundId,
        ?string $transactionId = null,
        ?string $description = null
    )
    {
        $this->session = $session;
        $this->amount = $amount;
        $this->roundId = $roundId;
        $this->transactionId = $transactionId;
        $this->description = $description;
    }

    /**
     * Execute the job.
     */
    public function handle(WalletBridgeService $walletService)
    {
        Log::info("Processing Async Wallet Win Response", [
            'session' => $this->session->session_token,
            'amount' => $this->amount,
            'round' => $this->roundId,
        ]);

        $result = $walletService->credit(
            $this->session,
            $this->amount,
            $this->roundId,
            $this->transactionId,
            $this->description,
            async: false
        );

        if ($result === false) {
            Log::error("Async Wallet Win Failed", [
                'session' => $this->session->session_token,
                'txn' => $this->transactionId
            ]);
            // Logic for retry or admin alert can go here
        }
    }
}

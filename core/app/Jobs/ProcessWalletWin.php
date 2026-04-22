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
use RuntimeException;
use Throwable;

class ProcessWalletWin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [10, 30, 60, 120, 240];

    protected int $sessionId;
    protected float $amount;
    protected string $roundId;
    protected ?string $transactionId;
    protected ?string $description;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $sessionId,
        float $amount,
        string $roundId,
        ?string $transactionId = null,
        ?string $description = null
    )
    {
        $this->sessionId = $sessionId;
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
        $session = TenantSession::with('tenant')->find($this->sessionId);
        if (!$session) {
            Log::warning("Async Wallet Win skipped: session not found", [
                'session_id' => $this->sessionId,
                'amount' => $this->amount,
                'round' => $this->roundId,
            ]);
            return;
        }

        Log::info("Processing Async Wallet Win Response", [
            'session' => $session->session_token,
            'amount' => $this->amount,
            'round' => $this->roundId,
        ]);

        $result = $walletService->credit(
            $session,
            $this->amount,
            $this->roundId,
            $this->transactionId,
            $this->description,
            async: false
        );

        if ($result === false) {
            Log::error("Async Wallet Win Failed", [
                'session' => $session->session_token,
                'txn' => $this->transactionId,
            ]);
            throw new RuntimeException('Async wallet credit failed.');
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Async Wallet Win permanently failed', [
            'session_id' => $this->sessionId,
            'round' => $this->roundId,
            'txn' => $this->transactionId,
            'error' => $e->getMessage(),
        ]);
    }
}

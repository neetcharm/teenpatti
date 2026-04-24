<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSession;
use App\Models\TenantTransaction;
use App\Models\User;
use App\Services\TenantConnectionManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TenantWebhookService
 *
 * Handles all outgoing HTTP calls to a tenant's webhook URL.
 *
 * Protocol (POST JSON):
 * 
 * Request fields sent to tenant:
 *   action         string  – balance | debit | credit | rollback
 *   player_id      string  – Tenant's own user identifier
 *   session_id     string  – Our session token
 *   game_id        string  – e.g. teen_patti
 *   currency       string  – e.g. INR
 *   amount         float   – (debit/credit/rollback only)
 *   round_id       string  – Unique round identifier
 *   transaction_id string  – Our unique transaction ID
 *   ref_txn_id     string  – (rollback only) original debit txn to reverse
 *   timestamp      int     – Unix timestamp
 *   signature      string  – HMAC-SHA256(api_secret, action|player_id|amount|round_id|timestamp)
 *
 * Expected response from tenant (JSON):
 *   status         string  – "ok" or "error"
 *   balance        float   – Player's new balance (after the action)
 *   transaction_id string  – Tenant's own txn ID (for reconciliation)
 *   message        string  – (error only) error description
 */
class TenantWebhookService
{
    private Tenant $tenant;
    private int    $timeoutSeconds = 10;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    //
    // Public API
    //
    /**
     * Fetch player's current balance from tenant.
     */
    public function balance(TenantSession $session): array
    {
        return $this->call('balance', $session, 0, null, null);
    }

    /**
     * Debit (bet placed): ask tenant to deduct player's balance.
     * Returns ['ok'=>bool, 'balance'=>float, 'txn_id'=>string, 'message'=>string]
     */
    public function debit(TenantSession $session, float $amount, string $roundId, ?string $txnId = null): array
    {
        $txnId = $txnId ?? $this->generateTxnId('db');
        return $this->call('debit', $session, $amount, $roundId, $txnId);
    }

    /**
     * Credit (win payout): ask tenant to add to player's balance.
     */
    public function credit(TenantSession $session, float $amount, string $roundId, ?string $txnId = null, ?string $refTxnId = null): array
    {
        $txnId = $txnId ?? $this->generateTxnId('cr');
        return $this->call('credit', $session, $amount, $roundId, $txnId, $refTxnId);
    }

    /**
     * Rollback a previous debit (e.g. system error).
     */
    public function rollback(TenantSession $session, string $originalTxnId, string $roundId): array
    {
        $txnId = $this->generateTxnId('rb');
        $rollbackAmount = $this->resolveRollbackAmount($originalTxnId);

        return $this->call('rollback', $session, $rollbackAmount, $roundId, $txnId, $originalTxnId);
    }

    //
    // Internal
    //
    private function call(
        string         $action,
        TenantSession  $session,
        float          $amount,
        ?string        $roundId,
        ?string        $txnId,
        ?string        $refTxnId = null
    ): array {
        $txnId = $txnId ?? $this->generateTxnId($action);

        // Internal balance mode: operate on our DB, no HTTP call
        if (($this->tenant->balance_mode ?? 'webhook') === 'internal') {
            return $this->callInternal($action, $session, $amount, $roundId, $txnId, $refTxnId);
        }

        $timestamp = time();

        // Build payload
        $payload = array_filter([
            'action'         => $action,
            'player_id'      => $session->player_id,
            'session_id'     => $session->session_token,
            'game_id'        => $session->game_id,
            'currency'       => $session->currency,
            'amount'         => $amount > 0 ? round($amount, 2) : null,
            'round_id'       => $roundId,
            'transaction_id' => $txnId,
            'ref_txn_id'     => $refTxnId,
            'timestamp'      => $timestamp,
        ], fn($v) => $v !== null);

        // Sign the payload
        $payload['signature'] = $this->sign($action, $session->player_id, $amount, $roundId ?? '', $timestamp);

        $balanceBefore = (float) $session->balance_cache;

        // Log transaction as pending (use tenant connection)
        $txnRecord = $this->txn()->fill([
            'tenant_id'       => $this->tenant->id,
            'session_id'      => $session->id,
            'action'          => $action,
            'player_id'       => $session->player_id,
            'round_id'        => $roundId,
            'game_id'         => $session->game_id,
            'our_txn_id'      => $txnId,
            'ref_txn_id'      => $refTxnId,
            'amount'          => $amount,
            'balance_before'  => $balanceBefore,
            'balance_after'   => $balanceBefore,
            'request_payload' => $payload,
            'status'          => 'pending',
        ]);
        $txnRecord->save();

        // Make the HTTP call
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                ->post($this->tenant->webhook_url, $payload);

            $body = $response->json() ?? [];

            if ($response->successful() && ($body['status'] ?? '') === 'ok') {
                $newBalance = (float) ($body['balance'] ?? $balanceBefore);

                $txnRecord->update([
                    'tenant_txn_id'    => $body['transaction_id'] ?? null,
                    'balance_after'    => $newBalance,
                    'response_payload' => $body,
                    'status'           => 'ok',
                ]);

                $this->syncLocalBalanceCache($session, $newBalance);

                return [
                    'ok'      => true,
                    'balance' => $newBalance,
                    'txn_id'  => $txnId,
                    'message' => 'ok',
                ];
            }

            // Tenant returned error
            $errorMsg = $body['message'] ?? ('HTTP ' . $response->status());
            $txnRecord->update([
                'response_payload' => $body,
                'status'           => 'failed',
                'error_message'    => $errorMsg,
            ]);

            Log::warning("TenantWebhook [{$action}] failed for tenant #{$this->tenant->id}: {$errorMsg}");

            return [
                'ok'      => false,
                'balance' => $balanceBefore,
                'txn_id'  => $txnId,
                'message' => $errorMsg,
            ];

        } catch (\Throwable $e) {
            $txnRecord->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("TenantWebhook [{$action}] exception for tenant #{$this->tenant->id}: " . $e->getMessage());

            return [
                'ok'      => false,
                'balance' => $balanceBefore,
                'txn_id'  => $txnId,
                'message' => 'Webhook connection failed.',
            ];
        }
    }

    //
    // Internal balance mode – no HTTP call, operate directly on our DB
    //
    private function callInternal(
        string        $action,
        TenantSession $session,
        float         $amount,
        ?string       $roundId,
        string        $txnId,
        ?string       $refTxnId
    ): array {
        $user = User::find($session->internal_user_id);
        if (!$user) {
            return ['ok' => false, 'balance' => (float) $session->balance_cache, 'txn_id' => $txnId, 'message' => 'Player not found'];
        }

        $balanceBefore = round((float) $user->balance, 2);
        $newBalance    = $balanceBefore;

        // Balance inquiry – no state change
        if ($action === 'balance') {
            return ['ok' => true, 'balance' => $balanceBefore, 'txn_id' => $txnId, 'message' => 'ok'];
        }

        // Debit (bet)
        if ($action === 'debit') {
            if ($balanceBefore < $amount) {
                return ['ok' => false, 'balance' => $balanceBefore, 'txn_id' => $txnId, 'message' => 'Insufficient balance'];
            }
            $newBalance = round($balanceBefore - $amount, 2);
        }

        // Credit (win)
        if ($action === 'credit') {
            $newBalance = round($balanceBefore + $amount, 2);
        }

        // Rollback (reverse a debit)
        if ($action === 'rollback') {
            $origTxn = $this->txn()->newQuery()->where('our_txn_id', $refTxnId)->where('action', 'debit')->first();
            $refundAmount = $origTxn ? round((float) $origTxn->amount, 2) : 0;
            $newBalance   = round($balanceBefore + $refundAmount, 2);
        }

        // Persist
        $user->balance = $newBalance;
        $user->save();

        $session->balance_cache = $newBalance;
        $session->save();

        $this->txn()->fill([
            'tenant_id'        => $this->tenant->id,
            'session_id'       => $session->id,
            'action'           => $action,
            'player_id'        => $session->player_id,
            'round_id'         => $roundId,
            'game_id'          => $session->game_id,
            'our_txn_id'       => $txnId,
            'tenant_txn_id'    => 'int_' . $txnId,
            'ref_txn_id'       => $refTxnId,
            'amount'           => $amount,
            'balance_before'   => $balanceBefore,
            'balance_after'    => $newBalance,
            'request_payload'  => ['mode' => 'internal', 'action' => $action, 'amount' => $amount],
            'response_payload' => ['status' => 'ok', 'balance' => $newBalance],
            'status'           => 'ok',
        ])->save();

        return ['ok' => true, 'balance' => $newBalance, 'txn_id' => $txnId, 'message' => 'ok'];
    }

    /**
     * HMAC-SHA256 signature for outgoing webhook calls.
     * Sign string: action|player_id|amount|round_id|timestamp
     * Key: decrypted webhook_secret (stored via Crypt::encrypt in tenants.webhook_secret)
     *
     * The tenant uses the same plain webhook_secret to verify our requests.
     */
    private function sign(string $action, string $playerId, float $amount, string $roundId, int $timestamp): string
    {
        $secret = $this->getWebhookSecret();
        $data   = implode('|', [$action, $playerId, number_format($amount, 2, '.', ''), $roundId, $timestamp]);
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Decrypt the stored webhook_secret. Falls back to api_key if not yet set.
     */
    private function getWebhookSecret(): string
    {
        if ($this->tenant->webhook_secret) {
            try {
                return \Illuminate\Support\Facades\Crypt::decrypt($this->tenant->webhook_secret);
            } catch (\Throwable $e) {
                // Already plain text (legacy)
                return $this->tenant->webhook_secret;
            }
        }
        return $this->tenant->api_key; // fallback for old records
    }

    private function generateTxnId(string $prefix): string
    {
        return strtolower($prefix) . '_' . now()->format('Ymd') . '_' . Str::random(12);
    }

    private function resolveRollbackAmount(string $originalTxnId): float
    {
        try {
            $debitTxn = $this->txn()->newQuery()
                ->where('our_txn_id', $originalTxnId)
                ->where('action', 'debit')
                ->first();

            if ($debitTxn) {
                return round((float) $debitTxn->amount, 2);
            }
        } catch (\Throwable $e) {
            Log::warning('Rollback amount lookup failed: ' . $e->getMessage(), [
                'tenant_id' => $this->tenant->id,
                'ref_txn_id' => $originalTxnId,
            ]);
        }

        return 0.0;
    }

    private function syncLocalBalanceCache(TenantSession $session, float $balance): void
    {
        $balance = round($balance, 2);

        $session->forceFill([
            'balance_cache' => $balance,
        ])->save();

        User::where('id', $session->internal_user_id)->update([
            'balance' => $balance,
        ]);
    }

    /** Returns a new TenantTransaction builder on the correct connection. */
    private function txn(): TenantTransaction
    {
        return TenantTransaction::onTenant($this->tenant);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TeenPattiTableManager
{
    private const BOT_FILL_SECONDS = 90;
    private const TABLE_TTL_SECONDS = 1800;
    private const CAPACITIES = [2, 4, 6];

    private bool $demoMode;

    public function __construct(bool $demoMode = false)
    {
        $this->demoMode = $demoMode;
    }

    public function assignUser(int $userId, string $displayName, int $capacity): array
    {
        $capacity = $this->normalizeCapacity($capacity);
        $state    = $this->loadState();
        $state    = $this->cleanupState($state);

        $existingTableId = $this->findUserTableId($state, $userId);
        if ($existingTableId && isset($state['tables'][$existingTableId])) {
            $existingTable = $state['tables'][$existingTableId];
            if ((int) $existingTable['capacity'] === $capacity) {
                $state = $this->applyBotFillRules($state);
                $this->saveState($state);

                return $this->buildResponse($state['tables'][$existingTableId], $userId);
            }
        }

        $state = $this->removeUserFromAllTables($state, $userId);

        $tableId = $this->findAvailableTableId($state, $capacity);
        if (!$tableId) {
            $tableId = $this->createTable($state, $capacity);
        }

        $this->assignUserToTable($state['tables'][$tableId], $userId, $displayName);

        $state = $this->applyBotFillRules($state);
        $state = $this->cleanupState($state);
        $this->saveState($state);

        return $this->buildResponse($state['tables'][$tableId], $userId);
    }

    public function getTableStatusForUser(int $userId, string $tableId): array
    {
        $state = $this->loadState();
        $state = $this->cleanupState($state);

        if (!isset($state['tables'][$tableId])) {
            return ['error' => 'Table not found. Please join again.'];
        }

        if (!$this->isUserInTable($state['tables'][$tableId], $userId)) {
            return ['error' => 'You are not assigned to this table.'];
        }

        $state['tables'][$tableId]['updated_at'] = time();
        $state = $this->applyBotFillRules($state);
        $this->saveState($state);

        return $this->buildResponse($state['tables'][$tableId], $userId);
    }

    public function validateUserCanPlay(int $userId, string $tableId, int $tableSize): array
    {
        $state = $this->loadState();
        $state = $this->cleanupState($state);
        $state = $this->applyBotFillRules($state);

        if (!isset($state['tables'][$tableId])) {
            return ['ok' => false, 'error' => 'Table not found. Please rejoin table.'];
        }

        $table = $state['tables'][$tableId];

        if ((int) $table['capacity'] !== $this->normalizeCapacity($tableSize)) {
            return ['ok' => false, 'error' => 'Table size mismatch. Please rejoin table.'];
        }

        if (!$this->isUserInTable($table, $userId)) {
            return ['ok' => false, 'error' => 'You are not part of this table.'];
        }

        $snapshot = $this->buildResponse($table, $userId);
        if (!$snapshot['can_play']) {
            return ['ok' => false, 'error' => 'Table is not ready yet. Waiting for players or bots.'];
        }

        $this->saveState($state);

        return ['ok' => true, 'table' => $snapshot];
    }

    private function assignUserToTable(array &$table, int $userId, string $displayName): void
    {
        if ($this->isUserInTable($table, $userId)) {
            return;
        }

        $occupiedSeats = array_column($table['seats'], 'seat_no');
        $seatNo        = 1;
        while (in_array($seatNo, $occupiedSeats, true)) {
            $seatNo++;
        }

        $table['seats'][] = [
            'seat_no'   => $seatNo,
            'type'      => 'user',
            'user_id'   => $userId,
            'name'      => $displayName ?: ('Player ' . $userId),
            'joined_at' => time(),
        ];

        $table['last_human_joined_at'] = time();
        $table['updated_at']           = time();
    }

    private function applyBotFillRules(array $state): array
    {
        $now = time();
        foreach ($state['tables'] as $tableId => $table) {
            $capacity     = (int) ($table['capacity'] ?? 2);
            $currentCount = count($table['seats'] ?? []);
            if ($currentCount >= $capacity) {
                continue;
            }

            $lastHumanJoin = (int) ($table['last_human_joined_at'] ?? $table['created_at'] ?? $now);
            if (($now - $lastHumanJoin) < self::BOT_FILL_SECONDS) {
                continue;
            }

            $occupiedSeats = array_column($table['seats'], 'seat_no');
            for ($seat = 1; $seat <= $capacity; $seat++) {
                if (in_array($seat, $occupiedSeats, true)) {
                    continue;
                }

                $state['tables'][$tableId]['seats'][] = [
                    'seat_no'   => $seat,
                    'type'      => 'bot',
                    'user_id'   => null,
                    'name'      => $this->randomBotName(),
                    'joined_at' => $now,
                ];
            }

            $state['tables'][$tableId]['updated_at'] = $now;
        }

        return $state;
    }

    private function cleanupState(array $state): array
    {
        $now = time();
        foreach ($state['tables'] as $tableId => $table) {
            $updatedAt = (int) ($table['updated_at'] ?? $table['created_at'] ?? $now);

            if (($now - $updatedAt) > self::TABLE_TTL_SECONDS) {
                unset($state['tables'][$tableId]);
                continue;
            }

            $humanCount = 0;
            foreach ($table['seats'] ?? [] as $seat) {
                if (($seat['type'] ?? 'bot') === 'user') {
                    $humanCount++;
                }
            }

            if ($humanCount === 0) {
                unset($state['tables'][$tableId]);
            }
        }

        return $state;
    }

    private function removeUserFromAllTables(array $state, int $userId): array
    {
        foreach ($state['tables'] as $tableId => $table) {
            $state['tables'][$tableId]['seats'] = array_values(array_filter($table['seats'] ?? [], function ($seat) use ($userId) {
                return !((int) ($seat['user_id'] ?? 0) === $userId && ($seat['type'] ?? 'bot') === 'user');
            }));

            $state['tables'][$tableId]['updated_at'] = time();
        }

        return $state;
    }

    private function buildResponse(array $table, int $userId): array
    {
        $capacity  = (int) ($table['capacity'] ?? 2);
        $seatsData = [];
        for ($i = 1; $i <= $capacity; $i++) {
            $seatsData[$i] = [
                'seat_no' => $i,
                'type'    => 'empty',
                'name'    => 'Waiting...',
                'is_you'  => false,
            ];
        }

        $userSeatNo  = null;
        $humanCount  = 0;
        $botCount    = 0;
        $filledSeats = 0;

        foreach ($table['seats'] ?? [] as $seat) {
            $seatNo = (int) ($seat['seat_no'] ?? 0);
            if ($seatNo < 1 || $seatNo > $capacity) {
                continue;
            }

            $isYou = ($seat['type'] ?? '') === 'user' && (int) ($seat['user_id'] ?? 0) === $userId;
            if ($isYou) {
                $userSeatNo = $seatNo;
            }

            if (($seat['type'] ?? '') === 'user') {
                $humanCount++;
            } elseif (($seat['type'] ?? '') === 'bot') {
                $botCount++;
            }

            $filledSeats++;
            $seatsData[$seatNo] = [
                'seat_no' => $seatNo,
                'type'    => $seat['type'] ?? 'empty',
                'name'    => $seat['name'] ?? 'Player',
                'is_you'  => $isYou,
            ];
        }

        $isReady       = $filledSeats >= $capacity;
        $waitRemaining = 0;
        if (!$isReady) {
            $waitSince    = (int) ($table['last_human_joined_at'] ?? $table['created_at'] ?? time());
            $waitRemaining = max(0, self::BOT_FILL_SECONDS - (time() - $waitSince));
        }

        return [
            'status'                 => 'success',
            'table_id'               => $table['id'],
            'table_code'             => $table['code'],
            'table_size'             => $capacity,
            'user_seat'              => $userSeatNo,
            'seats'                  => array_values($seatsData),
            'filled_seats'           => $filledSeats,
            'player_count'           => $humanCount,
            'bot_count'              => $botCount,
            'waiting_for'            => max(0, $capacity - $filledSeats),
            'is_ready'               => $isReady,
            'can_play'               => $isReady && $userSeatNo !== null,
            'wait_seconds_remaining' => $waitRemaining,
            'bot_fill_seconds'       => self::BOT_FILL_SECONDS,
            'message'                => $isReady
                ? 'Table is ready. Start betting now.'
                : "Waiting for players... next seat fills in {$waitRemaining}s",
        ];
    }

    private function createTable(array &$state, int $capacity): string
    {
        $id   = 'tp_' . Str::lower(Str::random(12));
        $code = 'TP' . $capacity . '-' . Str::upper(Str::random(4));

        $state['tables'][$id] = [
            'id'                 => $id,
            'code'               => $code,
            'capacity'           => $capacity,
            'created_at'         => time(),
            'updated_at'         => time(),
            'last_human_joined_at' => time(),
            'seats'              => [],
        ];

        return $id;
    }

    private function findAvailableTableId(array $state, int $capacity): ?string
    {
        $candidateIds = [];
        foreach ($state['tables'] as $tableId => $table) {
            if ((int) ($table['capacity'] ?? 0) !== $capacity) {
                continue;
            }

            $filledSeats = count($table['seats'] ?? []);
            if ($filledSeats >= $capacity) {
                continue;
            }

            $candidateIds[$tableId] = $filledSeats;
        }

        if (empty($candidateIds)) {
            return null;
        }

        arsort($candidateIds);
        return array_key_first($candidateIds);
    }

    private function findUserTableId(array $state, int $userId): ?string
    {
        foreach ($state['tables'] as $tableId => $table) {
            if ($this->isUserInTable($table, $userId)) {
                return $tableId;
            }
        }

        return null;
    }

    private function isUserInTable(array $table, int $userId): bool
    {
        foreach ($table['seats'] ?? [] as $seat) {
            if (($seat['type'] ?? '') === 'user' && (int) ($seat['user_id'] ?? 0) === $userId) {
                return true;
            }
        }

        return false;
    }

    private function loadState(): array
    {
        $state = Cache::get($this->cacheKey(), ['tables' => []]);
        if (!is_array($state) || !isset($state['tables']) || !is_array($state['tables'])) {
            return ['tables' => []];
        }

        return $state;
    }

    private function saveState(array $state): void
    {
        Cache::put($this->cacheKey(), $state, now()->addHours(6));
    }

    private function normalizeCapacity(int $capacity): int
    {
        if (!in_array($capacity, self::CAPACITIES, true)) {
            return 2;
        }

        return $capacity;
    }

    private function randomBotName(): string
    {
        $names = [
            'Raja',
            'Aman',
            'Kabir',
            'Vicky',
            'Arjun',
            'Rahul',
            'Dev',
            'Karan',
            'Yash',
            'Sameer',
            'Neel',
            'Rohan',
        ];

        return $names[array_rand($names)] . random_int(10, 99);
    }

    private function cacheKey(): string
    {
        return $this->demoMode ? 'teen_patti.tables.demo.v1' : 'teen_patti.tables.real.v1';
    }
}

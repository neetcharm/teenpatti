<?php

namespace App\Games;

use App\Constants\Status;

class TeenPatti extends Game
{
    protected $alias            = 'teen_patti';
    protected $resultShowOnStart = false;
    protected $extraValidationRule = [
        'choose' => 'required|string|in:silver,gold,diamond',
    ];

    protected function deck()
    {
        $suits = ['H', 'D', 'C', 'S'];
        $ranks = range(2, 10);
        $faceCards = ['J', 'Q', 'K', 'A'];
        $deck = [];

        foreach ($suits as $suit) {
            foreach ($ranks as $rank) {
                $deck[] = "{$rank}-{$suit}";
            }
            foreach ($faceCards as $face) {
                $deck[] = "{$face}-{$suit}";
            }
        }
        return $deck;
    }

    private function cardValue($rank)
    {
        $values = [
            '2'  => 2,  '3'  => 3,  '4'  => 4,  '5'  => 5,
            '6'  => 6,  '7'  => 7,  '8'  => 8,  '9'  => 9,
            '10' => 10, 'J'  => 11, 'Q'  => 12, 'K'  => 13,
            'A'  => 14,
        ];
        return $values[$rank] ?? 0;
    }

    private function rankHand($cards)
    {
        $ranks = [];
        $suits = [];

        foreach ($cards as $card) {
            $parts   = explode('-', $card);
            $ranks[] = $this->cardValue($parts[0]);
            $suits[] = $parts[1];
        }

        sort($ranks);

        $isFlush = count(array_unique($suits)) === 1;
        $isStraight = false;
        $highCard   = $ranks[2];

        if ($ranks[2] - $ranks[1] === 1 && $ranks[1] - $ranks[0] === 1) {
            $isStraight = true;
            $highCard   = $ranks[2];
        }
        if ($ranks[0] === 2 && $ranks[1] === 3 && $ranks[2] === 14) {
            $isStraight = true;
            $highCard   = 3;
        }

        if ($ranks[0] === $ranks[1] && $ranks[1] === $ranks[2]) {
            return 6 * 10000 + $ranks[2];
        }
        if ($isStraight && $isFlush) {
            return 5 * 10000 + $highCard;
        }
        if ($isStraight) {
            return 4 * 10000 + $highCard;
        }
        if ($isFlush) {
            return 3 * 10000 + $ranks[2] * 100 + $ranks[1] * 10 + $ranks[0];
        }
        if ($ranks[0] === $ranks[1] || $ranks[1] === $ranks[2]) {
            $pairValue = $ranks[1];
            $kicker    = ($ranks[0] === $ranks[1]) ? $ranks[2] : $ranks[0];
            return 2 * 10000 + $pairValue * 100 + $kicker;
        }
        return 1 * 10000 + $ranks[2] * 100 + $ranks[1] * 10 + $ranks[0];
    }

    private function rankLabel($cards)
    {
        $score = $this->rankHand($cards);
        $type  = (int) floor($score / 10000);
        return match ($type) {
            6 => 'Trail',
            5 => 'Pure Sequence',
            4 => 'Sequence',
            3 => 'Flush',
            2 => 'Pair',
            default => 'High Card',
        };
    }

    protected function gameResult()
    {
        $chosen = strtolower($this->request->choose);

        // Get the global manager to check current round result
        $demoMode = $this->demoPlay ? true : false;
        $manager  = new \App\Services\TeenPattiGlobalManager($demoMode);

        $round  = $manager->currentRound();
        $result = $manager->resolveRound($round);

        $winner = $result['winner'] ?? 'silver';
        $hands  = $result['hands']  ?? [];

        // Did this user bet on the winning placeholder?
        $winStatus = ($chosen === $winner) ? Status::WIN : Status::LOSS;

        // Calculate win amount: if won, the user gets their bet back + proportional share
        $winAmount = 0;
        if ($winStatus === Status::WIN && $result) {
            $userId     = (int) auth()->id();
            $userPayout = $result['user_payouts'][(string) $userId] ?? null;
            if ($userPayout) {
                $winAmount = $userPayout['payout'] ?? 0;
            } else {
                // Fallback: simple 1.8x (after 10% commission the effective rate = 90% of pool)
                $winAmount = $this->request->invest * 1.8;
            }
        }

        // Build hand card data for the frontend
        $seatCards = [
            1 => $hands['silver']  ?? [],
            2 => $hands['gold']    ?? [],
            3 => $hands['diamond'] ?? [],
        ];

        $handRanks = [
            1 => $this->rankLabel($hands['silver']  ?? ['2-H','3-D','5-C']),
            2 => $this->rankLabel($hands['gold']    ?? ['2-H','3-D','5-C']),
            3 => $this->rankLabel($hands['diamond'] ?? ['2-H','3-D','5-C']),
        ];

        $this->userSelect = $chosen;

        $this->extraResponseOnStart = array_merge($this->extraResponseOnStart, [
            'seatCards'  => $seatCards,
            'handRanks'  => $handRanks,
            'winner'     => $winner,
            'round'      => $round,
        ]);

        return [
            'win_status' => $winStatus,
            'result'     => $winner,
            'win_amount' => $winAmount,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Games\GamePlayer;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;

class PlayController extends Controller {
    public function playGame($alias, $isDemo = null) {
        if (!in_array($alias, liveGameAliases(), true)) {
            $notify[] = 'Game is not available';
            return responseError('not_found', $notify);
        }

        $game = Game::active()->where('alias', $alias)->first();
        if (!$game) {
            $notify[] = 'Game not found';
            return responseError('not_found', $notify);
        }
        $user = auth()->user();
        if ($isDemo && $isDemo !== 'demo') {
            $notify[] = 'Invalid request';
            return responseError('not_found', $notify);
        }
        $balance = ($isDemo === 'demo') ? @$user->demo_balance : @$user->balance;

        $notify[] = $game->name . ' game data';
        return responseSuccess('game_data', $notify, [
            'game'               => $game,
            'balance'            => showAmount($balance, currencyFormat: false),
            'imagePath'          => null,
            'winChance'          => null,
            'winPercent'         => [],
            'gesBon'             => [],
            'shortDesc'          => null,
            'cardFindingImgName' => [],
            'cardFindingImgPath' => null,
            'isDemo'             => $isDemo,
        ]);
    }

    public function investGame(Request $request, $alias, $isDemo = null) {
        if (!in_array($alias, liveGameAliases(), true)) {
            $notify[] = 'Game is not available';
            return responseError('not_found', $notify);
        }

        $gamePlayer = new GamePlayer($alias, $isDemo, true);
        return $gamePlayer->startGame();
    }

    public function gameEnd(Request $request, $alias, $isDemo = null) {
        if (!in_array($alias, liveGameAliases(), true)) {
            $notify[] = 'Game is not available';
            return responseError('not_found', $notify);
        }

        $gamePlayer = new GamePlayer($alias, $isDemo, true);
        return $gamePlayer->completeGame();
    }

}

<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * GameSecurityController — issues one-time action tokens for game bets.
 */
class GameSecurityController extends Controller
{
    public function issueToken(Request $request)
    {
        $token = Str::random(64);
        session(['game_action_token' => $token, 'game_action_token_at' => now()]);

        return response()->json([
            'action_token' => $token,
        ]);
    }
}

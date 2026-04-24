<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * TeenPattiWebViewController
 *
 * Entry point for the Android WebView integration.
 *
 * The Android app opens: GET /tp/{sanctum_token}
 *
 * This controller:
 *   1. Validates the Sanctum token (must be named 'webview')
 *   2. Logs the player into a web session
 *   3. Returns a fully self-contained Teen Patti game page
 *      (no header / footer / navigation – pure game UI)
 */
class TeenPattiWebViewController extends Controller
{
    public function serve(string $token)
    {
        // 1. Validate token
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken || $accessToken->name !== 'webview') {
            abort(403, 'Invalid or expired session link.');
        }

        /** @var \App\Models\User $user */
        $user = $accessToken->tokenable;

        if (!$user || $user->status != 1) {
            abort(403, 'Account is inactive.');
        }

        // 2. Log into web session (enables existing play routes)
        Auth::login($user, remember: false);

        // 3. Build URL config for the game page
        $balance    = showAmount((float) $user->balance, currencyFormat: false);
        $syncUrl    = route('user.play.teen_patti.global.sync');
        $investUrl  = route('user.play.invest', ['teen_patti']);
        $gameEndUrl = route('user.play.end', ['teen_patti']);
        $historyUrl = route('user.play.teen_patti.history');

        return view('tp_webview', compact('user', 'balance', 'syncUrl', 'investUrl', 'gameEndUrl', 'historyUrl'));
    }
}

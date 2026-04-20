<?php

namespace App\Http\Controllers\Api;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\ClientApp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ClientController
 *
 * Allows an external Android app (or any client) to authenticate one of their
 * users and receive a single-use WebView URL that opens the Teen Patti game.
 *
 * Integration steps for the Android client:
 *
 *   1. Call POST /api/client/player/token  (see playerToken() below)
 *   2. Receive { token, webview_url, player_balance }
 *   3. Open `webview_url` in an Android WebView – game runs live.
 *
 * Admin: create a row in `client_apps` via Tinker or admin panel:
 *   php artisan tinker
 *   \App\Models\ClientApp::create([
 *       'name'          => 'My App',
 *       'client_key'    => 'myapp_pub_key_xxxx',
 *       'client_secret' => bcrypt('myapp_plain_secret'),
 *       'status'        => 1,
 *   ]);
 */
class ClientController extends Controller
{
    /**
     * Issue a WebView session token for an external player.
     *
     * POST /api/client/player/token
     *
     * Request body (JSON):
     *   client_key         string   Your app's public key (from client_apps table)
     *   client_secret      string   Your app's plain-text secret (we verify the hash)
     *   external_user_id   string   Your platform's unique user identifier
     *   username           string   Display name shown in the game UI
     *   balance            float?   Optional: credit this amount to the player before opening game
     *
     * Response (JSON):
     *   success            bool
     *   message            string
     *   token              string   Sanctum token – pass to WebView via URL
     *   webview_url        string   Open this URL in Android WebView
     *   player_balance     float    Player's current balance (after optional top-up)
     *   player_name        string   Display name
     */
    public function playerToken(Request $request)
    {
        $request->validate([
            'client_key'       => 'required|string',
            'client_secret'    => 'required|string',
            'external_user_id' => 'required|string|max:100',
            'username'         => 'required|string|max:100',
            'balance'          => 'nullable|numeric|min:0',
        ]);

        // ── 1. Verify client app ────────────────────────────────────────────
        $app = ClientApp::where('client_key', $request->client_key)
            ->where('status', 1)
            ->first();

        if (!$app || !Hash::check($request->client_secret, $app->client_secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid client credentials.',
            ], 401);
        }

        // ── 2. Find or create internal user for this external player ────────
        // Username pattern: clt{app_id}_{slugified_external_id}
        $internalUsername = 'clt' . $app->id . '_' . Str::slug($request->external_user_id, '_');
        $internalEmail    = 'clt' . $app->id . '_' . md5($request->external_user_id) . '@tp.internal';

        $user = User::where('username', $internalUsername)->first();

        if (!$user) {
            $user                   = new User();
            $user->firstname        = $request->username;
            $user->lastname         = '';
            $user->email            = $internalEmail;
            $user->username         = $internalUsername;
            $user->password         = Hash::make(Str::random(40));
            $user->ref_by           = 0;
            $user->status           = Status::USER_ACTIVE;   // 1
            $user->ev               = Status::VERIFIED;       // 1 – email verified
            $user->sv               = Status::VERIFIED;       // 1 – mobile verified
            $user->tv               = Status::VERIFIED;       // 1 – token verified
            $user->ts               = Status::DISABLE;        // 0 – 2FA off
            $user->kv               = Status::KYC_VERIFIED;   // 1 – KYC passed
            $user->profile_complete = Status::YES;            // 1
            $user->demo_balance     = 0;
            $user->balance          = 0;
            $user->save();
        } else {
            // Keep display name in sync with the client platform
            $user->firstname = $request->username;
            $user->save();
        }

        // ── 3. Optional balance top-up ──────────────────────────────────────
        if ($request->filled('balance') && (float) $request->balance > 0) {
            $user->balance += (float) $request->balance;
            $user->save();
        }

        // ── 4. Rotate WebView token (one active token per user) ─────────────
        $user->tokens()->where('name', 'webview')->delete();
        $plainToken = $user->createToken('webview')->plainTextToken;

        // ── 5. Build the WebView URL ─────────────────────────────────────────
        $webviewUrl = url('/tp/' . $plainToken);

        return response()->json([
            'success'        => true,
            'message'        => 'Player token issued.',
            'token'          => $plainToken,
            'webview_url'    => $webviewUrl,
            'player_balance' => (float) $user->balance,
            'player_name'    => $user->firstname,
        ]);
    }
}

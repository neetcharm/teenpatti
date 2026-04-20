<?php

namespace App\Modules\GameEngine;

use App\Modules\WalletBridge\WalletBridgeService;
use Illuminate\Support\Facades\App;

class GameResolver
{
    /**
     * Resolve the appropriate game engine implementation.
     * 
     * @param string $alias
     * @return GameInterface
     * @throws \Exception
     */
    public static function resolve(string $alias): GameInterface
    {
        $wallet = App::make(WalletBridgeService::class);

        // 1. Check for modular implementations
        $modularGames = [
            'teen_patti' => TeenPattiGame::class,
        ];

        if (isset($modularGames[$alias])) {
            $class = $modularGames[$alias];
            return new $class($wallet);
        }

        // 2. Add more modular games here...

        // 3. Fallback or throw error if not supported in SaaS
        throw new \Exception("Game [{$alias}] is not yet modularized for SaaS WebView.");
    }
}

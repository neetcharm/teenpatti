<?php

namespace App\Games;

use Exception;

class GamePlayer {
    private $games = [
        'teen_patti' => TeenPatti::class,
    ];

    private $playingGame;
    private $isDemo;
    private $fromApi;

    public function __construct($alias, $isDemo, $fromApi = false) {
        $this->playingGame = $alias;
        $this->isDemo = $isDemo;
        $this->fromApi = $fromApi;
    }

    public function startGame() {
        $gameName = $this->playingGame;
        try {
            $gameClass = $this->games[$gameName];
        } catch (\Exception $e) {
            if ($this->fromApi) {
                $notify[] = "The game $gameName not found";
                return responseError('not_found', $notify);
            }
            throw new Exception("The game $gameName not found");
        }
        $instance = new $gameClass;
        $instance->demoPlay = $this->isDemo ? true : false;
        $instance->fromApi = $this->fromApi ?? false;
        return $instance->play();
    }

    public function completeGame() {
        $gameName = $this->playingGame;
        try {
            $gameClass = $this->games[$gameName];
        } catch (\Exception $e) {
            if ($this->fromApi) {
                $notify[] = "The game $gameName not found";
                return responseError('not_found', $notify);
            }
            throw new Exception("The game $gameName not found");
        }
        $instance = new $gameClass;
        $instance->demoPlay = $this->isDemo ? true : false;
        $instance->fromApi = $this->fromApi ?? false;
        return $instance->complete();
    }
}

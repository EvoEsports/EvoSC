<?php

namespace esc\controllers;


use esc\classes\Log;
use esc\models\Player;

class PlayerController
{
    public static function initialize()
    {
        HookController::add('ManiaPlanet.PlayerConnect', '\esc\controllers\PlayerController::playerConnect');
        HookController::add('ManiaPlanet.PlayerInfoChanged', '\esc\controllers\PlayerController::playerInfoChanged');
    }

    public static function playerConnect($login): Player
    {
        try {
            $player = Player::whereLogin($login)->firstOrFail();
        } catch (\Exception $e) {
            $player = new Player();
            $player->login = $login;
            $player->save();

            Log::info("New player ($login)");
        }

        $player->increment('Visits');

        return $player;
    }

    public static function playerInfoChanged($infoplayerInfo)
    {
        try {
            $player = Player::whereLogin($infoplayerInfo['Login'])->firstOrFail();
        } catch (\Exception $e) {
            $player = self::playerConnect($infoplayerInfo['Login']);
        }

        $player->update($infoplayerInfo);
    }
}
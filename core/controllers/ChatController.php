<?php

namespace esc\controllers;


use esc\classes\Log;
use esc\models\Player;

class ChatController
{
    public static function initialize()
    {
        HookController::add('PlayerChat', 'esc\controllers\ChatController::logChat');
    }

    public static function logChat(Player $player, $text, $isRegisteredCmd)
    {
        Log::chat($player->nick(true), $text);
    }
}
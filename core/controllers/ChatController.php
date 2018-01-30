<?php

namespace esc\controllers;


use esc\classes\Log;

class ChatController
{
    public static function initialize()
    {
        HookController::add('ManiaPlanet.PlayerChat', 'esc\controllers\ChatController::logChat');
    }

    //int PlayerUid, string Login, string Text, bool IsRegistredCmd
    public static function logChat($playerId, $login, $text, $isRegisteredCmd){
        Log::chat($login, $text);
    }
}
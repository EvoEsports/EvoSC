<?php

namespace esc\Modules;


use esc\Classes\ManiaLinkEvent;
use esc\Controllers\ChatController;
use esc\Models\Player;

class Warning
{
    public function __construct()
    {
        ManiaLinkEvent::add('warn', [self::class, 'warnPlayer'], 'warn');
    }

    public static function warnPlayer(Player $player, string $targetLogin, string $message)
    {
        $target = Player::whereLogin($targetLogin)->first();

        if ($target) {
            ChatController::message($target, '_warning', "You have been warned by $player: ", secondary($message));
        }
    }
}
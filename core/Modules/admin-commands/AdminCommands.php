<?php

use esc\controllers\ChatController;
use esc\controllers\MapController;
use esc\models\Player;

class AdminCommands
{
    public function __construct()
    {
        ChatController::addCommand('skip', 'AdminCommands::next', 'Skip map instantly', '//', ['Admin', 'SuperAdmin']);
    }

    public static function next(Player $player)
    {
        ChatController::messageAll("$player->NickName \$z\$s$%sskips map.", config('color.primary'));
        MapController::next();
    }
}
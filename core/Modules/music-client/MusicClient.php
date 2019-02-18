<?php

namespace esc\Modules\MusicClient;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class MusicClient
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [MusicClient::class, 'playerConnect']);

        // KeyController::createBind('X', [self::class, 'reload']);
    }

    /**
     * Hook: PlayerConnect
     *
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        Template::show($player, 'music-client.widget');
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::playerConnect($player);
    }
}
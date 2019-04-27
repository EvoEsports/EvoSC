<?php

namespace esc\Modules\MusicClient;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use esc\Modules\KeyBinds;

class MusicClient
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);

        KeyBinds::add('reload_music_client', 'Reload music client.', [self::class, 'reload'], 'F2', 'ms');
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
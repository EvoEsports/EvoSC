<?php

namespace esc\Modules\MusicClient;

use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\RestClient;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use GuzzleHttp\Exception\ConnectException;

class MusicClient
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [MusicClient::class, 'playerConnect']);

       // KeyController::createBind('Y', [self::class, 'reload']);
    }

    /**
     * Hook: PlayerConnect
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
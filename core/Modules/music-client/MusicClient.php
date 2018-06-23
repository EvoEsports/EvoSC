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
    private static $music;

    public function __construct()
    {
        Hook::add('PlayerConnect', [MusicClient::class, 'playerConnect']);

        KeyController::createBind('Y', [MusicClient::class, 'playerConnect']);
    }

    /**
     * Hook: PlayerConnect
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'music-client.widget');
    }
}
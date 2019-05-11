<?php

namespace esc\Modules\MusicClient;

use esc\Classes\ChatCommand;
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

        ChatCommand::add('/music', [self::class, 'searchMusic'], 'Open and search the music list.');
    }

    /**
     * Hook: PlayerConnect
     *
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        Template::show($player, 'music-client.music-client');
    }

    public static function searchMusic(Player $player, $cmd, ...$arguments)
    {
        $query = implode(' ', $arguments);
        Template::show($player, 'music-client.search-command', compact('query'));
    }
}
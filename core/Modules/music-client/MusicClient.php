<?php

namespace esc\Modules\MusicClient;

use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Map;
use esc\Models\Player;
use esc\Modules\KeyBinds;

class MusicClient
{
    private static $music;
    private static $song;

    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('EndMap', [self::class, 'setNextSong']);

        ChatCommand::add('/music', [self::class, 'searchMusic'], 'Open and search the music list.');

        $musicJson   = file_get_contents(config('music.url'));
        self::$music = collect(json_decode($musicJson));

        KeyBinds::add('reload_music_client', 'Reload music.', [self::class, 'reload'], 'F2', 'ms');
    }

    public static function setNextSong(Map $map = null)
    {
        self::$song = self::$music->random(1)->first();
        Server::setForcedMusic(true, config('music.url') . '?song=' . urlencode(self::$song->file));
        $song = json_encode(self::$song);
        Template::showAll('music-client.start-song', compact('song'));
    }

    /**
     * Hook: PlayerConnect
     *
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        Template::show($player, 'music-client.music-client');
        $song = json_encode(self::$song);
        Template::showAll('music-client.start-song', compact('song'));
    }

    public static function searchMusic(Player $player, $cmd, ...$arguments)
    {
        $query = implode(' ', $arguments);
        Template::show($player, 'music-client.search-command', compact('query'));
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::playerConnect($player);
    }
}
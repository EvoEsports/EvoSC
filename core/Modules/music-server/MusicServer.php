<?php

use esc\classes\File;
use esc\classes\Hook;
use esc\classes\RestClient;
use esc\classes\Template;
use esc\models\Map;
use Illuminate\Support\Collection;

class MusicServer
{
    private static $music;

    public function __construct()
    {
        $this->readFiles();

        Template::add('music', File::get(__DIR__ . '/Templates/music.latte.xml'));

        Hook::add('EndMap', 'MusicServer::setNextSong');
        Hook::add('BeginMap', 'MusicServer::displayCurrentSong');
    }

    private function readFiles()
    {
        $musicJson = RestClient::get(config('music.server') . 'dir.php', [
            'query' => [
                'token' => config('music.token')
            ]
        ])->getBody();

        $musicFiles = json_decode($musicJson);

        MusicServer::setMusicFiles(collect($musicFiles));
    }

    public static function setMusicFiles(Collection $files)
    {
        self::$music = $files;
    }

    public static function setNextSong(Map $map = null)
    {
        $randomMusicFile = self::$music->random();
        $url = config('music.server') . '/' . $randomMusicFile;
        \esc\controllers\ServerController::getRpc()->setForcedMusic(true, $url);
    }

    public static function displayCurrentSong(Map $map = null)
    {
        $songInformation = \esc\controllers\ServerController::getRpc()->getForcedMusic();
        $songInformation = str_replace(config('music.server'), '', $songInformation->url);
        $songInformation = preg_replace('/\.ogg$/', '', $songInformation);
        Template::showAll('music', ['song' => $songInformation]);
    }
}
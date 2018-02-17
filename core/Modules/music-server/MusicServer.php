<?php

use esc\classes\RestClient;
use Illuminate\Support\Collection;

class MusicServer
{
    private static $music;

    public function __construct()
    {
        $this->readFiles();
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
}
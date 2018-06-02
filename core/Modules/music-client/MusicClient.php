<?php

namespace esc\Modules\MusicClient;

use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\RestClient;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use GuzzleHttp\Exception\ConnectException;

class MusicClient
{
    private static $music;

    public function __construct()
    {
        $this->getMusicLibrary();

        Hook::add('PlayerConnect', [MusicClient::class, 'playerConnect']);
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

    /**
     * Get total songs count
     * @return mixed
     */
    public static function getSongsCount()
    {
        return self::$music->count();
    }

    /**
     * Output music as ManiaScript-Array
     * @return string
     */
    public static function musicToManiaScriptArray()
    {
        $music = self::$music->map(function ($song) {
            $search = strtolower("$song->title$song->artist");
            return sprintf('["%s","%s","%s","%s","%s"]', $song->url, $song->title, $song->artist, $song->length, $search);
        })->implode(',');

        return sprintf('[%s]', $music);
    }

    /**
     * Get music lib form server
     */
    private function getMusicLibrary()
    {
        Log::info("Loading music...");

        $wait = 5;

        try {
            $res = RestClient::get(config('music.server'), ['connect_timeout' => $wait]);
        } catch (ConnectException $e) {
            Log::logAddLine('MusicClient', 'Failed to connect to server after ' . $wait . ' seconds');
            return;
        }

        if ($res->getStatusCode() != 200) {
            Log::logAddLine('Music server', 'Failed to get music: ' . $res->getReasonPhrase(), true);
            return;
        }

        $musicJson = $res->getBody()->getContents();

        try {
            self::$music = collect(json_decode($musicJson))->map([MusicClient::class, 'addSongUrls']);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::logAddLine('Music server', 'Failed to get music: ' . $e->getMessage(), true);
        }
    }

    public function addSongUrls($song)
    {
        $song->url = preg_replace('/\?token=.+/', '', config('music.server')) . $song->file;
        return $song;
    }
}
<?php

namespace esc\Modules\MusicClient;

use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\RestClient;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Collection;

class MusicClient
{
    private static $music;

    public function __construct()
    {
        $this->readFiles();

        ManiaLinkEvent::add('ms.play', 'MusicClient::playSong');
        ManiaLinkEvent::add('ms.recommend', 'MusicClient::recommend');
        ManiaLinkEvent::add('music.next', 'MusicClient::nextSong');
        ManiaLinkEvent::add('ms.menu.showpage', 'MusicClient::displayMusicMenu');

        ChatController::addCommand('music', 'MusicClient::displayMusicMenu', 'Select music to play');

        Hook::add('PlayerConnect', 'MusicClient::playerConnect');
    }

    public static function recommend(Player $player, $songId)
    {
        $song = self::$music->get($songId);
        ChatController::messageAll('_info', $player, ' recommends song ', secondary($song->title), ' by ', secondary($song->artist));
    }

    public static function playerConnect(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'music-client.widget');
    }

    public static function getRandomSong()
    {
        return self::$music->random();
    }

    public static function getSongsCount()
    {
        return self::$music->count();
    }

    public static function musicToManiaScriptArray()
    {
        $music = self::$music->map(function ($song) {
            $search = strtolower("$song->title$song->artist");
            return sprintf('["%s","%s","%s","%s","%s"]', $song->url, $song->title, $song->artist, $song->length, $search);
        })->implode(',');

        return sprintf('[%s]', $music);
    }

    /**
     * Display the music menu
     * @param Player $player
     * @param int $page
     */
    public static function displayMusicMenu(Player $player, $page = null)
    {
        $perPage    = 19;
        $songsCount = self::$music->count();
        $songs      = self::$music->sortBy('title')->forPage($page ?? 0, $perPage);

        $music      = Template::toString('music-client.menu', ['songs' => $songs]);
        $pagination = Template::toString('components.pagination', ['pages' => ceil($songsCount / $perPage), 'action' => 'ms.menu.showpage', 'page' => $page]);

        Template::show($player, 'components.modal', [
            'id'            => '♫ Music',
            'width'         => 180,
            'height'        => 97,
            'content'       => $music,
            'pagination'    => $pagination,
            'showAnimation' => isset($page) ? false : true
        ]);
    }

    /**
     * Plays a song
     * @param Player $callee
     * @param $songId
     */
    public static function playSong(Player $callee, $songId = null)
    {
        if ($songId) {
            $song = self::$music->get($songId);
        }

        if (!isset($song)) {
            $song = self::$music->random();
        }

        Template::show($callee, 'music-client.song', compact('song'));
    }

    /**
     * Goes to next song
     * @param Player $callee
     * @param $songId
     */
    public static function nextSong(Player $callee)
    {
        self::playSong($callee);
    }

    /**
     * Sets the music files
     * @param Collection $songs
     */
    private static function setMusicFiles(Collection $songs)
    {
        $url = preg_replace('/\/\?token=[\w]+/', '', config('music.server'));

        $songs->each(function (&$song) use ($url) {
            $song->url = $url . '/' . $song->file;
        });

        self::$music = $songs;
    }

    /**
     * Read available music form server
     */
    private function readFiles()
    {
        Log::info("Loading music...");

        try {
            $res = RestClient::get(config('music.server'), ['connect_timeout' => 2]);
            $musicJson = $res->getBody()->getContents();
        } catch (\Exception $e) {
            Log::logAddLine('Music', 'Server connection failed: ' . $e->getMessage());
            $musicJson = file_get_contents(cacheDir('music.json'));
        }

        if(!json_decode($musicJson)){
            $musicJson = file_get_contents(cacheDir('music.json'));
        }

        try {
            MusicClient::setMusicFiles(collect(json_decode($musicJson)));
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::logAddLine('Music server', 'Failed to get music, make sure you have the url and token set', true);
        }
    }
}
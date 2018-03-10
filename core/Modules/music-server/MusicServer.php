<?php

use esc\classes\Database;
use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\ManiaLinkEvent;
use esc\classes\RestClient;
use esc\Classes\Server;
use esc\classes\Template;
use esc\controllers\ChatController;
use esc\models\Map;
use esc\models\Player;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;

class MusicServer
{
    private static $startLoad;
    private static $music;
    private static $currentSong;
    private static $songQueue;

    public function __construct()
    {
//        $this->createTables();

//        include_once 'Models/Song.php';

        $this->readFiles();

        self::$songQueue = new Collection();

        Template::add('music', File::get(__DIR__ . '/Templates/music.latte.xml'));
        Template::add('music.menu', File::get(__DIR__ . '/Templates/menu.latte.xml'));

        ManiaLinkEvent::add('ms.hidemenu', 'MusicServer::hideMusicMenu');
        ManiaLinkEvent::add('ms.juke', 'MusicServer::queueSong');
        ManiaLinkEvent::add('ms.menu.showpage', 'MusicServer::displayMusicMenu');

        ChatController::addCommand('music', 'MusicServer::displayMusicMenu', 'Opens the music menu where you can queue music.');

        Hook::add('EndMatch', 'MusicServer::setNextSong');
        Hook::add('BeginMap', 'MusicServer::beginMap');
        Hook::add('PlayerConnect', 'MusicServer::displaySongWidget');
    }

    private function createTables()
    {
        Database::create('songs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->string('artist')->nullable();
            $table->string('album')->nullable();
            $table->string('year')->nullable();
            $table->string('length')->nullable();
            $table->string('url')->unique();
            $table->timestamps();
        });
    }

    /**
     * Show music widget on map start
     * @param array ...$args
     */
    public static function beginMap(...$args)
    {
        self::displaySongWidget();
    }

    /**
     * Gets current song
     * @return null|Song
     */
    public static function getCurrentSong(): ?Song
    {
        $songInformation = \esc\classes\Server::getForcedMusic();
        $url = $songInformation->url;
        return self::$music->where('url', $url)->first();
    }

    /**
     * Sets next song to be played
     * @param array ...$args
     */
    public static function setNextSong(...$args)
    {
        if (self::$songQueue && count(self::$songQueue) > 0) {
            $song = self::$songQueue->shift()['song'];
        } else {
            $song = self::$music->random();
        }

        Server::setForcedMusic(true, $song->url);
    }

    /**
     * Display the onscreen widget
     * @param Player|null $player
     */
    public static function displaySongWidget(Player $player = null)
    {
        if (!self::$music) {
            Log::warning("Music not loaded, can not display widget.");
            return;
        }

        $songInformation = \esc\classes\Server::getForcedMusic();
        $url = $songInformation->url;
        $song = self::$music->where('url', $url)->first();

        if ($song) {
            if ($player) {
                Template::show($player, 'music', ['song' => $song]);
            } else {
                Template::showAll('music', ['song' => $song]);
            }

            self::$currentSong = $song;
        } else {
            Log::error("Invalid song");
        }
    }

    /**
     * Display the music menu
     * @param Player $callee
     * @param int $page
     */
    public static function displayMusicMenu(Player $callee, $page = 1)
    {
        $page = (int)$page;
        if ($page == 0) {
            $page = 1;
        }

        $songs = self::$music->sortBy('title')->forPage($page, 15);
        $pages = ceil(count(self::$music) / 15);

        $queue = self::$songQueue->sortBy('time')->take(9);
        Template::show($callee, 'music.menu', ['songs' => $songs, 'queue' => $queue, 'pages' => $pages, 'page' => $page]);
    }

    /**
     * Hides the music menu
     * @param Player $triggerer
     */
    public static function hideMusicMenu(Player $triggerer)
    {
        Template::hide($triggerer, 'music.menu');
    }

    /**
     * Adds a song to the music-jukebox
     * @param Player $callee
     * @param $songId
     */
    public static function queueSong(Player $callee, $songId)
    {
        $song = self::$music->get($songId);

        if ($song) {
            self::$songQueue->push([
                'song' => $song,
                'wisher' => $callee,
                'time' => time()
            ]);

            ChatController::messageAll($callee, ' added song ', secondary($song->title ?: ''), ' to the jukebox');
        }

        Template::hide($callee, 'music.menu');
    }

    /**
     * Sets the music files
     * @param Collection $songs
     */
    private static function setMusicFiles(Collection $songs)
    {
        foreach ($songs as $song) {
            $song->url = config('music.server') . '/' . $song->file;
        }

//        $totalTime = (float) ((time() + microtime()) - self::$startLoad);
//        Log::info("Finished loading music. " . sprintf("Took %.3fs.", $totalTime));
        Log::info("Finished loading music.");

        self::$music = $songs;
    }

    /**
     * Read available music form server
     */
    private function readFiles()
    {
        Log::info("Loading music...");

        self::$startLoad = (float)microtime() + time();

        $musicJson = RestClient::get(config('music.server'))->getBody()->getContents();

        $musicFiles = json_decode($musicJson);

        MusicServer::setMusicFiles(collect($musicFiles));
    }
}
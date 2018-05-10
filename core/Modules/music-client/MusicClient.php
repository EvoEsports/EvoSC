<?php

namespace esc\Modules\MusicClient;

use Carbon\Carbon;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use esc\Models\Song;
use Illuminate\Support\Collection;

class MusicClient
{
    private static $startLoad;
    private static $music;
    private static $currentSong;
    private static $songQueue;

    public function __construct()
    {
        $this->readFiles();

        self::$songQueue = new Collection();

        ManiaLinkEvent::add('ms.play', 'MusicClient::playSong');
        ManiaLinkEvent::add('ms.recommend', 'MusicClient::recommend');
        ManiaLinkEvent::add('music.next', 'MusicClient::nextSong');
        ManiaLinkEvent::add('ms.menu.showpage', 'MusicClient::displayMusicMenu');

        ChatController::addCommand('music', 'MusicClient::displayMusicMenu', 'Opens the music menu where you can queue music.');

        Hook::add('EndMatch', 'MusicClient::setNextSong');
        Hook::add('PlayerConnect', 'MusicClient::playerConnect');
    }

    public static function recommend(Player $player, $songId)
    {
        $song = self::$music->get($songId);
        ChatController::messageAll('_info', $player, ' recommends song ', secondary($song->title), ' by ', secondary($song->artist));
    }

    public static function playerConnect(Player $player)
    {
        $content = Template::toString('music-client.music', [
            'hideSpeed' => $player->user_settings->ui->hideSpeed ?? null
        ]);

        Template::show($player, 'components.icon-box', [
            'id'      => 'music-widget',
            'content' => $content,
            'config'  => config('ui.music')
        ]);

        self::playSong($player);
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

        $queue = self::$songQueue->sortBy('time')->take(9);

        $music      = Template::toString('music-client.menu', ['songs' => $songs, 'queue' => $queue]);
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

        if (!$song) {
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

        Log::info("Finished loading music.");

        self::$music       = $songs;
        self::$currentSong = $songs->random();
    }

    /**
     * Read available music form server
     */
    private function readFiles()
    {
        Log::info("Loading music...");

        if (File::exists(cacheDir('music.json'))) {
            $musicJson = file_get_contents(cacheDir('music.json'));
        } else {
            $res       = RestClient::get(config('music.server'));
            $musicJson = $res->getBody()->getContents();

            $musicData = collect([
                'date' => Carbon::now(),
                'data' => $musicJson
            ]);

            File::put(cacheDir('music.json'), $musicData->toJson());
        }

        try {
            $musicFiles = json_decode($musicJson);
            MusicClient::setMusicFiles(collect($musicFiles));
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::logAddLine('Music server', 'Failed to get music, make sure you have the url and token set', true);
        }
    }
}
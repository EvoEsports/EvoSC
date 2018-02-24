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
    private static $music;
    private static $currentSong;
    private static $songQueue;

    public function __construct()
    {
        $this->createTables();

        include_once 'Models/Song.php';

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
            $table->string('hash')->primary();
            $table->string('title')->default('unkown');
            $table->string('artist')->default('unkown');
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
        $songInformation = \esc\classes\Server::getRpc()->getForcedMusic();
        $hash = md5($songInformation->url);
        return Song::where('hash', $hash)->first();
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

        Server::getRpc()->setForcedMusic(true, $song->url);
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

        $songInformation = \esc\classes\Server::getRpc()->getForcedMusic();
        $hash = md5($songInformation->url);
        $song = Song::where('hash', $hash)->first();

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

        $songs = Song::orderBy('title')->get()->forPage($page, 15);
        $pages = ceil(Song::count() / 15);

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
     * @param $songHash
     */
    public static function queueSong(Player $callee, $songHash)
    {
        $song = Song::where('hash', $songHash)->first();

        if ($song) {
            self::$songQueue->push([
                'song' => $song,
                'wisher' => $callee,
                'time' => time()
            ]);

            ChatController::messageAllNew($callee, ' added song ', $song, ' to the jukebox');
        }

        Template::hide($callee, 'music.menu');
    }

    /**
     * Get song information from remote url
     * @param string $url
     * @return mixed
     */
    private static function getSongInformation(string $url)
    {
        $remoteUrl = str_replace(' ', '%20', $url);
        if ($fp_remote = fopen($remoteUrl, 'rb')) {
            $localtempfilename = tempnam(cacheDir(), 'getID3');
            if ($fp_local = fopen($localtempfilename, 'wb')) {
                while ($buffer = fread($fp_remote, 8192)) {
                    fwrite($fp_local, $buffer);
                }
                fclose($fp_local);

                $getID3 = new getID3;
                $ThisFileInfo = $getID3->analyze($localtempfilename);

                unlink($localtempfilename);
            }
            fclose($fp_remote);

            if (isset($song)) {
                return $song;
            }
        }

        if (isset($ThisFileInfo)) {
            return $ThisFileInfo;
        }
    }

    /**
     * Sets the music files
     * @param Collection $files
     */
    private static function setMusicFiles(Collection $files)
    {
        $songsToDelete = Song::whereNotIn('url', $files)->get();

        foreach ($songsToDelete as $song) {
//            Log::info("Delete song: $song->name - $song->artist");
        }

        $songs = new Collection();

        Log::info("Loading music...");

        foreach ($files as $file) {
            $remoteUrl = config('music.server') . $file;

            $song = Song::where('url', $remoteUrl)->first();

            if (!$song) {
                Log::info("Loading song $remoteUrl");

                $songInfo = self::getSongInformation($remoteUrl);

                try {
                    $song = Song::firstOrCreate([
                        'title' => $songInfo['tags']['vorbiscomment']['title'][0],
                        'artist' => $songInfo['tags']['vorbiscomment']['artist'][0],
                        'album' => $songInfo['tags']['vorbiscomment']['album'][0],
                        'year' => $songInfo['tags']['vorbiscomment']['date'][0],
                        'length' => $songInfo['playtime_string'],
                        'url' => $remoteUrl,
                        'hash' => md5($remoteUrl)
                    ]);
                } catch (\Exception $e) {
                    try{
                        $song = Song::firstOrCreate([
                            'title' => $songInfo['audio']['tags']['title'][0],
                            'artist' => $songInfo['audio']['tags']['artist'][0],
                            'album' => $songInfo['audio']['tags']['album'][0],
                            'year' => $songInfo['audio']['tags']['date'][0],
                            'length' => $songInfo['playtime_string'],
                            'url' => $remoteUrl,
                            'hash' => md5($remoteUrl)
                        ]);
                    }catch(\Exception $e){
                        Log::warning("Could not get id3-tags for song: $file");
                        var_dump([
                            'title' => $songInfo['tags']['vorbiscomment']['title'][0],
                            'artist' => $songInfo['tags']['vorbiscomment']['artist'][0],
                            'album' => $songInfo['tags']['vorbiscomment']['album'][0],
                            'year' => $songInfo['tags']['vorbiscomment']['date'][0],
                            'length' => $songInfo['playtime_string'],
                            'url' => $remoteUrl,
                            'hash' => md5($remoteUrl)
                        ]);
                        continue;
                    }
                }
            }

            $songs->push($song);
        }

        Log::info("Finished loading music.");

        self::$music = $songs;
    }

    /**
     * Read available music form server
     */
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
}
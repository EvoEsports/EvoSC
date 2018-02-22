<?php

use esc\classes\Database;
use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\ManiaLinkEvent;
use esc\classes\RestClient;
use esc\classes\Template;
use esc\controllers\ChatController;
use esc\models\Map;
use esc\models\Player;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\Paginator;
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
        \esc\classes\Timer::create('reload.music.template', 'MusicServer::reloadTemplates', '1s');

        Hook::add('EndMap', 'MusicServer::setNextSong');
        Hook::add('BeginMap', 'MusicServer::displayCurrentSong');
        Hook::add('PlayerConnect', 'MusicServer::displaySongWidget');
    }

    public static function reloadTemplates()
    {
        Template::add('music.menu', File::get(__DIR__ . '/Templates/menu.latte.xml'));
        \esc\classes\Timer::create('reload.music.template', 'MusicServer::reloadTemplates', '1s');
    }

    private function createTables()
    {
        Database::create('songs', function (Blueprint $table) {
            $table->string('hash')->primary();
            $table->string('title')->default('unkown');
            $table->string('artist')->default('unkown');
            $table->string('url')->unique();
            $table->timestamps();
        });
    }

    public static function getCurrentSong(): ?Song
    {
        return self::$currentSong;
    }

    public static function setNextSong(Map $map = null)
    {
        $randomMusicFile = self::$music->random();
        $url = config('music.server') . '/' . $randomMusicFile;
        \esc\controllers\ServerController::getRpc()->setForcedMusic(true, $url);
    }

    public static function displayCurrentSong(Map $map = null)
    {
        self::displaySongWidget();
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

        $songInformation = \esc\controllers\ServerController::getRpc()->getForcedMusic();

        $song = self::getCurrentSong();

        if (!$song) {
            $hash = md5($songInformation->url);
            $song = Song::where('hash', $hash)->first();
        }

        if ($player) {
            Template::show($player, 'music', ['song' => $song]);
        } else {
            Template::showAll('music', ['song' => $song]);
        }

        self::$currentSong = $song;
    }

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

        if($song){
            self::$songQueue->push([
                'song' => $song,
                'wisher' => $callee,
                'time' => time()
            ]);

            ChatController::messageAll('%s $z$s$%s added Song $%s%s $%sto the jukebox', $callee->NickName, config('color.primary'), config('color.secondary'), $song->title, config('color.primary'));
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
                        'title' => $songInfo['audio']['tags']['title'][0],
                        'artist' => $songInfo['audio']['tags']['artist'][0],
                        'album' => $songInfo['audio']['tags']['album'][0],
                        'year' => $songInfo['audio']['tags']['date'][0],
                        'length' => $songInfo['playtime_string'],
                        'url' => $remoteUrl,
                        'hash' => md5($remoteUrl)
                    ]);
                } catch (\Exception $e) {
                    if (preg_match('/(.+) \- (.+)\.ogg/', $file, $matches)) {
                        $song = Song::firstOrCreate([
                            'title' => $matches[2],
                            'artist' => $matches[1],
                            'url' => $remoteUrl,
                            'hash' => md5($remoteUrl)
                        ]);
                    } else {
                        $song = Song::firstOrCreate([
                            'title' => preg_replace('/\.ogg$/', '', $file),
                            'url' => $remoteUrl,
                            'hash' => md5($remoteUrl)
                        ]);
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
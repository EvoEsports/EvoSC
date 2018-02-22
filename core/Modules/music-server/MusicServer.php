<?php

use esc\classes\File;
use esc\classes\Hook;
use esc\classes\RestClient;
use esc\classes\Template;
use esc\models\Map;
use esc\models\Player;
use Illuminate\Support\Collection;

class MusicServer
{
    private static $music;
    private static $currentSong;

    public function __construct()
    {
        include_once __DIR__ . '/Song.php';

        $this->readFiles();

        File::createDirectory(cacheDir('song-informations'));

        Template::add('music', File::get(__DIR__ . '/Templates/music.latte.xml'));

        Hook::add('EndMap', 'MusicServer::setNextSong');
        Hook::add('BeginMap', 'MusicServer::displayCurrentSong');
        Hook::add('PlayerConnect', 'MusicServer::displaySongWidget');
    }

    public static function getCurrentSong(): ?Song
    {
        return self::$currentSong;
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
        self::displaySongWidget();
    }

    /**
     * Display the onscreen widget
     * @param Player|null $player
     */
    public static function displaySongWidget(Player $player = null)
    {
        $songInformation = \esc\controllers\ServerController::getRpc()->getForcedMusic();
        $song = self::getCurrentSong();

        if (!$song || $songInformation->url != $song->url) {

            $cacheFile = cacheDir('song-informations/' . md5($songInformation->url));
            if (File::exists($cacheFile)) {
                $data = File::get($cacheFile);
                $song = Song::createFromCachefile($data);
            } else {
                $remotefilename = str_replace(' ', '%20', $songInformation->url);
                if ($fp_remote = fopen($remotefilename, 'rb')) {
                    $localtempfilename = tempnam(cacheDir(), 'getID3');
                    if ($fp_local = fopen($localtempfilename, 'wb')) {
                        while ($buffer = fread($fp_remote, 8192)) {
                            fwrite($fp_local, $buffer);
                        }
                        fclose($fp_local);

                        $getID3 = new getID3;
                        $ThisFileInfo = $getID3->analyze($localtempfilename);
                        $song = new Song($ThisFileInfo, $songInformation->url);

                        self::saveToCache($song);

                        unlink($localtempfilename);
                    }
                    fclose($fp_remote);
                }
            }

        }

        if ($player) {
            Template::show($player, 'music', ['song' => $song]);
        } else {
            Template::showAll('music', ['song' => $song]);
        }

        self::$currentSong = $song;
    }

    /**
     * Cache song information
     * @param Song $song
     */
    private static function saveToCache(Song $song)
    {
        $hash = md5($song->url);
        File::put(cacheDir('song-informations/' . $hash), json_encode($song));
    }
}
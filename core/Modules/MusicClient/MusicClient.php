<?php

namespace EvoSC\Modules\MusicClient;

use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class MusicClient extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static $music;

    /**
     * @var stdClass
     */
    private static stdClass $song;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        $url = config('music.url');

        if (!$url) {
            self::enableMusicDisabledNotice();

            return;
        }

        $promise = RestClient::getAsync($url, [
            'connect_timeout' => 30
        ]);

        $promise->then(function (ResponseInterface $response) {
            if ($response->getStatusCode() != 200) {
                Log::warning('Failed to fetch music list.');
                self::enableMusicDisabledNotice();

                return;
            }

            $musicJson = $response->getBody()->getContents();
            self::$music = collect(json_decode($musicJson));
            self::$song = self::$music->where('file', '=', urldecode(preg_replace('/^.+\?song=/', '', Server::getForcedMusic()->url)))->first();

            Log::info('Library loaded successfully.');

            Hook::add('PlayerConnect', [self::class, 'playerConnect']);
            Hook::add('BeginMap', [self::class, 'setNextSong']);

            ChatCommand::add('/music', [self::class, 'searchMusic'], 'Open and search the music list.');

            InputSetup::add('reload_music_client', 'Reload music.', [self::class, 'reload'], 'F2', 'ms');
        }, function (RequestException $e) {
            Log::error('Failed to fetch music list: ' . $e->getMessage());
            self::enableMusicDisabledNotice();
        });
    }

    private static function enableMusicDisabledNotice()
    {
        Hook::add('PlayerConnect', function (Player $player) {
            warningMessage('Music server not reachable, custom music is disabled.')->send($player);
        });
    }

    public static function searchMusic(Player $player, string $search = '')
    {
        Template::show($player, 'MusicClient.search-command', compact('search'), false, 20);
    }

    public static function setNextSong()
    {
        self::$song = self::$music->random(1)->first();
        Server::setForcedMusic(true, config('music.url') . '?song=' . urlencode(self::$song->file));

        if (self::$song) {
            Template::showAll('MusicClient.start-song', ['song' => json_encode(self::$song)], 60);
        }
    }

    public static function sendMusicLib(Player $player)
    {
        $server = config('music.url');
        $chunks = self::$music->chunk(200);

        Template::show($player, 'MusicClient.send-music', [
            'server' => $server,
            'music' => $chunks,
        ], false, 2);
    }

    public static function showMusicList(Player $player)
    {
        Template::show($player, 'MusicClient.list');
    }

    /**
     * Hook: PlayerConnect
     *
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        self::sendMusicLib($player);
        self::showMusicList($player);
        Template::show($player, 'MusicClient.music-client');

        $url = Server::getForcedMusic()->url;

        if ($url) {
            $file = urldecode(preg_replace('/.+\?song=/', '', Server::getForcedMusic()->url));
            $song = json_encode(self::$music->where('file', $file)->first());
        } else {
            $song = json_encode(self::$song);
        }

        if ($song != 'null') {
            Template::show($player, 'MusicClient.start-song', compact('song'), false, 180);
        }
    }
}
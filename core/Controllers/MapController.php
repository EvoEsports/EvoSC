<?php

namespace esc\Controllers;


use esc\Classes\Config;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\MapQueueItem;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\Vote;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\FileException;

class MapController
{
    private static $mapsPath;
    private static $currentMap;
    private static $queue;
    private static $addedTime = 0;
    private static $timeLimit = 10;

    public static function init()
    {
        self::$timeLimit = floor(Server::getRpc()->getTimeAttackLimit()['CurrentValue'] / 60000);

        self::loadMaps();

        self::$queue    = new Collection();
        self::$mapsPath = config('server.base') . '/UserData/Maps/';

        Hook::add('PlayerConnect', [MapController::class, 'displayMapWidget']);
        Hook::add('BeginMap', [MapController::class, 'beginMap']);
        Hook::add('EndMatch', [MapController::class, 'endMatch']);

        ChatController::addCommand('skip', [MapController::class, 'skip'], 'Skips map instantly', '//', 'skip');
        ChatController::addCommand('settings', [MapController::class, 'settings'], 'Load match settings', '//', 'ban');
        ChatController::addCommand('add', [MapController::class, 'addMap'], 'Add a map from mx. Usage: //add \<mxid\>', '//', 'map.add');
        ChatController::addCommand('res', [MapController::class, 'forceReplay'], 'Queue map for replay', '//', 'map.replay');
        ChatController::addCommand('addtime', [MapController::class, 'addTimeManually'], 'Adds time (you can also substract)', '//', 'time');
    }

    /**
     * Reset time on round end for example
     */
    public static function resetTime()
    {
        self::$addedTime = 0;
        self::updateRoundtime(self::$timeLimit * 60);
    }

    /**
     * Add time to the counter
     *
     * @param int $minutes
     */
    public static function addTime(int $minutes = 10)
    {
        self::$addedTime = self::$addedTime + $minutes;
        $totalNewTime    = (self::$timeLimit + self::$addedTime) * 60;
        self::updateRoundtime($totalNewTime);
    }

    public static function addTimeManually(Player $player, $cmd, int $amount)
    {
        self::addTime($amount);
    }


    private static function updateRoundtime(int $timeInSeconds)
    {
        $settings                = \esc\Classes\Server::getModeScriptSettings();
        $settings['S_TimeLimit'] = $timeInSeconds;
        \esc\Classes\Server::setModeScriptSettings($settings);
    }

    /**
     * Hook: EndMatch
     *
     * @param $rankings
     * @param $winnerteam
     */
    public static function endMatch()
    {
        $request = self::$queue->shift();

        if ($request) {
            Log::info("Setting next map: " . $request->map->Name);
            Server::chooseNextMap($request->map->filename);
            ChatController::messageAll("", ' Next map is ', $request->map, ' as requested by ', $request->issuer);
        } else {
            $nextMap = self::getNext();
            ChatController::messageAll("", ' Next map is ', $nextMap);
        }
    }

    /*
     * Hook: BeginMap
     */
    public static function beginMap(Map $map)
    {
        $map->increment('plays');
        $map->update(['last_played' => Carbon::now()]);

        self::loadMxDetails($map);

        foreach (finishPlayers() as $player) {
            $player->setScore(0);
        }

        self::$currentMap = $map;
        self::displayMapWidget();

        self::resetTime();
    }

    /**
     * Gets current map
     *
     * @return Map|null
     */
    public static function getCurrentMap(): ?Map
    {
        return self::$currentMap;
    }

    /**
     * Get all queued maps
     *
     * @return Collection
     */
    public static function getQueue(): Collection
    {
        return self::$queue->sortBy('timeRequested');
    }

    public static function deleteMap(Player $player, Map $map)
    {
        try {
            Server::removeMap($map->filename);
        } catch (FileException $e) {
            Log::error($e);
        }

        $deleted = File::delete(Config::get('server.maps') . '/' . $map->filename);

        if ($deleted) {
            ChatController::messageAll('Admin removed map ', $map);
            try {
                $map->delete();
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));
                ChatController::messageAll('_info', $player->group, ' ', $player, ' removed map ', $map, ' permanently');
            } catch (\Exception $e) {
                Log::logAddLine('MapController', 'Failed to deleted map: ' . $e->getMessage());
            }
        }
    }

    public static function disableMap(Player $player, Map $map)
    {
        try {
            Server::removeMap($map->filename);
        } catch (FileException $e) {
            Log::error($e);
        }

        $map->update(['enabled' => false]);
        Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));

        ChatController::messageAll('_info', $player->group, ' ', $player, ' disabled map ', $map);
    }

    /**
     * Ends the match and goes to the next round
     */
    public static function goToNextMap()
    {
        Server::nextMap();
    }

    /**
     * Gets the next played map
     *
     * @return Map
     */
    public static function getNext(): Map
    {
        $first = self::$queue->first();

        if ($first) {
            $map = self::$queue->first()->map;
        } else {
            $mapInfo = Server::getNextMapInfo();
            $map     = Map::where('uid', $mapInfo->uId)
                          ->first();
        }

        return $map;
    }

    /**
     * Admins skip method
     *
     * @param Player $player
     */
    public static function skip(Player $player)
    {
        ChatController::messageAll($player->group, ' ', $player, ' skips map');
        MapController::goToNextMap();
        Vote::stopVote();
    }

    /**
     * Force replay a round at end of match
     *
     * @param Player $player
     */
    public static function forceReplay(Player $player)
    {
        $currentMap = self::getCurrentMap();

        if (self::getQueue()
                ->contains('map.uid', $currentMap->uid)) {
            ChatController::message($player, 'Map is already being replayed');

            return;
        }

        self::$queue->push(new MapQueueItem($player, $currentMap, 0));
        ChatController::messageAll($player, ' queued map ', $currentMap, ' for replay');
        self::displayMapWidget();
    }

    /**
     * Add a map to the queue
     *
     * @param Player $player
     * @param Map $map
     */
    public static function queueMap(Player $player, Map $map, $arg = null)
    {
        if (self::getQueue()
                ->where('player', $player)
                ->isNotEmpty() && !$player->isAdmin()) {
            ChatController::message($player, "You already have a map in queue", []);

            return;
        }

        self::$queue->push(new MapQueueItem($player, $map, time()));

        Server::chooseNextMap(self::getNext()->filename);

        ChatController::messageAll($player, ' juked map ', $map);
        Log::info("$player->NickName juked map " . $map->gbx->Name);

        self::displayMapWidget();

        Hook::fire('QueueUpdated', self::$queue);
    }

    private static function getGbxInformation($filename)
    {
        $cmd = config('server.base') . '/ManiaPlanetServer /parsegbx="' . config('server.base') . '/UserData/Maps/' . str_replace('\\', DIRECTORY_SEPARATOR, $filename) . '"';
        return shell_exec($cmd);
    }

    /**
     * Loads maps from server directory
     */
    private static function loadMaps()
    {
        Log::logAddLine('MapController', 'Loading maps...');

        //Get loaded maps
        $maps = collect(Server::getMapList());

        //get array with the uids
        $enabledMapsuids = $maps->pluck('uid');

        foreach ($maps as $mapInfo) {
            $map = Map::where('uid', $mapInfo->uId)
                      ->get()
                      ->first();

            if (!$map) {
                //Map does not exist, create it
                $author = Player::where('Login', $mapInfo->author)->first();

                if ($author) {
                    $authorId = $author->id;
                } else {
                    $authorId = Player::insertGetId([
                        'Login'    => $mapInfo->author,
                        'NickName' => $mapInfo->author
                    ]);
                }

                $gbxInfo = self::getGbxInformation($mapInfo->fileName);

                $map = Map::updateOrCreate([
                    'author'   => $authorId,
                    'enabled'  => true,
                    'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'filename' => $mapInfo->fileName,
                    'uid'      => json_decode($gbxInfo)->MapUid,
                ]);
            }

            echo ".";
        }

        echo "\n";

        //Disable maps
        Map::whereNotIn('uid', $enabledMapsuids)
           ->update(['enabled' => false]);

        //Enable loaded maps
        Map::whereIn('uid', $enabledMapsuids)
           ->update(['enabled' => true]);
    }

    /**
     * Display the map widget
     *
     * @param Player|null $player
     */
    public static function displayMapWidget(Player $player = null)
    {
        $currentMap = self::getCurrentMap();

        if ($player) {
            self::showWidget($player, $currentMap);
        } else {
            onlinePlayers()->each(function (Player $player) use ($currentMap) {
                self::showWidget($player, $currentMap);
            });
        }
    }

    private static function showWidget(Player $player, Map $currentMap = null)
    {
        if (!$currentMap) {
            return;
        }

        $content = Template::toString('map', [
            'map' => $currentMap
        ]);

        $hideScript = Template::toString('scripts.hide', ['hideSpeed' => $player->user_settings->ui->hideSpeed ?? null, 'config' => config('ui.map')]);

        Template::show($player, 'components.icon-box', [
            'id'         => 'map-widget',
            'content'    => $content,
            'config'     => config('ui.map'),
            'hideScript' => $hideScript
        ]);
    }

    public static function loadMxDetails(Map $map, bool $overwrite = false)
    {
        if ($map->mx_details != null && !$overwrite) {
            return;
        }

        $result = RestClient::get('https://api.mania-exchange.com/tm/maps/' . $map->uid);

        if ($result->getStatusCode() != 200) {
            Log::logAddLine('MapController', 'Failed to fetch MX details: ' . $result->getReasonPhrase());
            return;
        }

        $map->update(['mx_details' => $result->getBody()->getContents()]);

        Log::logAddLine('MapController', 'Updated MX details for track: ' . $map->gbx->Name);

        $map = Map::whereUid($map->uid)->first();

        $result = RestClient::get('https://api.mania-exchange.com/tm/tracks/worldrecord/' . $map->mx_details->TrackID);

        if ($result->getStatusCode() != 200) {
            Log::logAddLine('MapController', 'Failed to fetch MX world record: ' . $result->getReasonPhrase());
            return;
        }

        $map->update(['mx_world_record' => $result->getBody()->getContents()]);

        Log::logAddLine('MapController', 'Updated MX world record for track: ' . $map->gbx->Name);
    }

    /**
     * Add map from MX
     *
     * @param string[] ...$arguments
     */
    public static function addMap(Player $player, $cmd, string ...$arguments)
    {
        foreach ($arguments as $mxId) {
            $mxId = (int)$mxId;

            if ($mxId == 0) {
                Log::warning("Requested map with invalid id: " . $mxId);
                ChatController::messageAll("Requested map with invalid id: " . $mxId);

                return;
            }

            $map = Map::where('mx_details->TrackID', $mxId)
                      ->get()
                      ->first();

            if ($map) {
                ChatController::messageAll($map, ' already exists');
                continue;
            }

            $response = RestClient::get('http://tm.mania-exchange.com/tracks/download/' . $mxId);

            if ($response->getStatusCode() != 200) {
                Log::error("ManiaExchange returned with non-success code [$response->getStatusCode()] " . $response->getReasonPhrase());
                ChatController::messageAll("Can not reach mania exchange.");

                return;
            }

            if ($response->getHeader('Content-Type')[0] != 'application/x-gbx') {
                Log::warning('Not a valid GBX.');

                return;
            }

            $filename = preg_replace('/^attachment; filename="(.+)"$/', '\1', $response->getHeader('content-disposition')[0]);
            $filename = html_entity_decode(trim($filename), ENT_QUOTES | ENT_HTML5);

            $mapFolder = self::$mapsPath;
            File::put("$mapFolder$filename", $response->getBody());

            $gbxInfo = self::getGbxInformation($filename);
            $gbx     = json_decode($gbxInfo);

            $author = Player::whereLogin($gbx->AuthorLogin)->first();

            if (!$author) {
                $authorId = Player::insertGetId([
                    'Login'    => $gbx->AuthorLogin,
                    'NickName' => $gbx->AuthorLogin,
                ]);
            } else {
                $authorId = $author->id;
            }

            $map = Map::firstOrCreate([
                'uid'      => $gbx->MapUid,
                'author'   => $authorId,
                'filename' => $filename,
                'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                'enabled'  => 1
            ]);

            try {
                Server::addMap($map->filename);
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));
            } catch (\Exception $e) {
                Log::warning("Map $map->filename already added.");
            }

            ChatController::messageAll('New map added: ', $map);
        }
    }

    public static function settings(Player $player, $cmd, $filename)
    {
        try {
            Server::loadMatchSettings(matchSettings($filename));
        } catch (\Exception $e) {
            Log::logAddLine('MatchSettings', 'Failed to load matchsettings: ' . $filename);
        }
    }

    /**
     * @return int
     */
    public static function getTimeLimit(): int
    {
        return self::$timeLimit;
    }

    /**
     * @return int
     */
    public static function getAddedTime(): int
    {
        return self::$addedTime;
    }
}
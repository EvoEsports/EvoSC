<?php

namespace esc\Controllers;


use esc\Classes\Config;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\MapQueueItem;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\Player;
use esc\Modules\QuickButtons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\FileException;
use mysql_xdevapi\Exception;

class MapController implements ControllerInterface
{
    private static $mapsPath;
    private static $currentMap;
    private static $queue;
    private static $addedTime = 0;
    private static $timeLimit = 10;

    public static function init()
    {
        self::$timeLimit = floor(Server::rpc()->getTimeAttackLimit()['CurrentValue'] / 60000);

        self::$queue    = new Collection();
        self::$mapsPath = Server::getMapsDirectory();

        self::loadMaps();

        Hook::add('BeginMap', [MapController::class, 'beginMap']);
        Hook::add('BeginMatch', [MapController::class, 'beginMatch']);
        Hook::add('EndMatch', [MapController::class, 'endMatch']);

        ChatController::addCommand('skip', [MapController::class, 'skip'], 'Skips map instantly', '//', 'map_skip');
        ChatController::addCommand('settings', [MapController::class, 'settings'], 'Load match settings', '//', 'matchsettings_load');
        ChatController::addCommand('add', [MapController::class, 'addMap'], 'Add a map from mx. Usage: //add \<mxid\>', '//', 'map_add');
        ChatController::addCommand('res', [MapController::class, 'forceReplay'], 'Queue map for replay', '//', 'map_replay');
        ChatController::addCommand('addtime', [MapController::class, 'addTimeManually'], 'Adds time (you can also substract)', '//', 'time');

        ManiaLinkEvent::add('map.skip', [MapController::class, 'skip'], 'map.skip');
        ManiaLinkEvent::add('map.replay', [MapController::class, 'forceReplay'], 'map.replay');
        ManiaLinkEvent::add('map.reset', [MapController::class, 'resetRound'], 'map.reset');

        AccessRight::createIfNonExistent('map_queue_recent', 'Queue recently played tracks.');
        AccessRight::createIfNonExistent('map_skip', 'Skip map instantly.');
        AccessRight::createIfNonExistent('map_add', 'Add map permanently.');
        AccessRight::createIfNonExistent('map_delete', 'Delete map permanently.');
        AccessRight::createIfNonExistent('map_disable', 'Disable map.');
        AccessRight::createIfNonExistent('map_replay', 'Force a replay.');
        AccessRight::createIfNonExistent('map_reset', 'Reset round.');
        AccessRight::createIfNonExistent('matchsettings_load', 'Load matchsettings.');
        AccessRight::createIfNonExistent('matchsettings_edit', 'Edit matchsettings.');
        AccessRight::createIfNonExistent('time', 'Change the countdown time.');

        KeyController::createBind('Q', [self::class, 'addMinute'], 'time');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Skip Map', 'map.skip', 'map_skip');
            QuickButtons::addButton('', 'Replay Map', 'map.replay', 'map_replay');
            QuickButtons::addButton('', 'Reset Round', 'map.reset', 'map_reset');
        }
    }

    public static function addMinute(Player $player)
    {
        self::addTime(1);
    }

    /**
     * Reset time on round end for example
     */
    public static function resetTime()
    {
        $settings = \esc\Classes\Server::getModeScriptSettings();
        \esc\Classes\Server::setModeScriptSettings($settings);
        self::$addedTime = 0;
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

        Hook::fire('TimeLimitUpdated', $timeInSeconds);
    }

    /**
     * Hook: EndMatch
     *
     * @param $rankings
     * @param $winnerteam
     */
    public static function endMatch()
    {
        $request = self::$queue->first();

        if ($request) {
            $nextMapUid = Server::rpc()->getNextMapInfo()->uId;

            if ($request->map->Uid != $nextMapUid) {
                //Preloaded map does not match top of queue anymore
                $request = self::$queue->shift();
            }

            Log::info("Setting next map: " . $request->map);
            Server::chooseNextMap($request->map->filename);
            ChatController::message(onlinePlayers(), '_info', '$fff', ' Upcoming map ', secondary($request->map), ' requested by ',
                $request->issuer);
        } else {
            $nextMap = self::getNext();
            ChatController::message(onlinePlayers(), '_info', '$fff', ' Upcoming map ', secondary($nextMap));
        }
    }

    /*
     * Hook: BeginMap
     */
    public static function beginMap(Map $map)
    {
        Map::where('id', '!=', $map->id)->increment('cooldown');

        $map->increment('plays');
        $map->update([
            'last_played' => Carbon::now(),
            'cooldown'    => 0,
        ]);

        self::loadMxDetails($map);

        foreach (finishPlayers() as $player) {
            $player->setScore(0);
        }

        self::$currentMap = $map;
    }

    public static function beginMatch()
    {
        self::resetTime();
    }

    /**
     * Gets current map
     *
     * @return Map|null
     */
    public static function getCurrentMap(): ?Map
    {
        if (!self::$currentMap) {
            throw new Exception("Current map is null");
        }

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
            ChatController::message(onlinePlayers(), '_info', $player, ' removed map ', $map);
            try {
                $map->delete();
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));
                ChatController::message(onlinePlayers(), '_info', $player, ' deleted map ', secondary($map), 'permanently.');
                Hook::fire('MapPoolUpdated');
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

        ChatController::message(onlinePlayers(), '_info', $player->group, ' ', $player, ' disabled map ', secondary($map));
        Hook::fire('MapPoolUpdated');
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
    public static function skip(Player $player = null)
    {
        if ($player) {
            ChatController::message(onlinePlayers(), '_info', $player, ' skips map');
        }

        MapController::goToNextMap();
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
        ChatController::message(onlinePlayers(), '_info', $player, ' queued map ', $currentMap, ' for replay');
    }

    /**
     * Adds a map to the queue
     *
     * @param \esc\Models\Player $player
     * @param \esc\Models\Map    $map
     * @param null               $arg
     *
     * @return null
     */
    public static function queueMap(Player $player, Map $map, $arg = null)
    {
        if (self::getQueue()
                ->where('player', $player)
                ->isNotEmpty()) {

            //Player already has map in queue
            ChatController::message($player, '_warning', "You already have a map in queue.");

            return self::$queue;
        }

        if ($map->cooldown < config('server.map-cooldown') && $player->hasAccess('map_queue_recent')) {
            ChatController::message($player, '_info', 'Can not juke recently played tracks.');

            return self::$queue;
        }

        self::$queue->push(new MapQueueItem($player, $map, time()));

        //Preload map => faster map change
        Server::chooseNextMap(self::getNext()->filename);

        ChatController::message(onlinePlayers(), '_info', $player, ' juked map ', $map);
        Log::logAddLine('MapController', "$player juked map " . $map->gbx->Name);
        Hook::fire('QueueUpdated', self::$queue);

        return self::$queue;
    }

    public static function unqueueMap(Map $map): Collection
    {
        self::$queue = self::getQueue()->reject(function (MapQueueItem $mqi) use ($map) {
            return $mqi->map == $map;
        })->values();

        return self::$queue;
    }

    private static function getGbxInformation($filename): string
    {
        $absolute = Server::getMapsDirectory() . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $filename);
        $cmd      = Server::GameDataDirectory() . '/../ManiaPlanetServer /parsegbx="' . $absolute . '"';

        return shell_exec($cmd);
    }

    /**
     * Loads maps from server directory
     */
    public static function loadMaps()
    {
        Log::logAddLine('MapController', 'Loading maps...');

        //Get loaded matchsettings maps
        $maps = collect(Server::getMapList());

        //get array with the uids
        $enabledMapsuids = $maps->pluck('uId');

        foreach ($maps as $mapInfo) {
            $mapFile = self::$mapsPath . $mapInfo->fileName;

            if (!File::exists($mapFile)) {
                throw new Exception("File $mapFile not found.");
                continue;
            }

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
                        'NickName' => $mapInfo->author,
                    ]);
                }

                $gbxInfo = self::getGbxInformation($mapInfo->fileName);

                $map = Map::updateOrCreate([
                    'author'   => $authorId,
                    'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'filename' => $mapInfo->fileName,
                    'uid'      => json_decode($gbxInfo)->MapUid,
                ]);
            }

            if (!$map->gbx) {
                $gbxInfo = self::getGbxInformation($mapInfo->fileName);
                $map->update([
                    'gbx' => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'uid' => json_decode($gbxInfo)->MapUid,
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

        $data = $result->getBody()->getContents();

        if ($data == '[]') {
            Log::logAddLine('MapController', 'No MX information available for: ' . $map->gbx->Name);

            return;
        }

        $map->update(['mx_details' => $data]);
        Log::logAddLine('MapController', 'Updated MX details for track: ' . $map->gbx->Name);

        $mxDetails = json_decode($data);

        if (count($mxDetails) == 0) {
            Log::logAddLine('MapController', 'Failed to fetch MX world record: mxDetails is empty.');

            return;
        }

        $mxDetails = $mxDetails[0];

        $result = RestClient::get('https://api.mania-exchange.com/tm/tracks/worldrecord/' . $mxDetails->TrackID);

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
                ChatController::message($player, "Requested map with invalid id: " . $mxId);

                return;
            }

            $map = Map::getByMxId($mxId);

            if ($map && File::exists(self::$mapsPath . $map->filename)) {
                ChatController::message($player, '_warning', secondary($map), ' already exists');
                continue;
            }

            $response = RestClient::get('http://tm.mania-exchange.com/tracks/download/' . $mxId);

            if ($response->getStatusCode() != 200) {
                Log::error("ManiaExchange returned with non-success code [$response->getStatusCode()] " . $response->getReasonPhrase());
                ChatController::message($player, "Can not reach mania exchange.");

                return;
            }

            if ($response->getHeader('Content-Type')[0] != 'application/x-gbx') {
                Log::warning('Not a valid GBX.');

                return;
            }

            $filename = preg_replace('/^attachment; filename="(.+)"$/', '\1',
                $response->getHeader('content-disposition')[0]);
            $filename = html_entity_decode(trim($filename), ENT_QUOTES | ENT_HTML5);
            $filename = str_replace('..', '.', $filename);

            $mapFolder = self::$mapsPath;
            $body      = $response->getBody();
            $absolute  = "$mapFolder$filename";

            File::put($absolute, $body);

            if (!File::exists($absolute)) {
                ChatController::message($player, '_warning', "Map download ($mxId) failed.");
                continue;
            }


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

            if ($map) {
                $map->update([
                    'author'   => $authorId,
                    'filename' => $filename,
                    'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'enabled'  => 1,
                ]);
            } else {
                $map = Map::firstOrCreate([
                    'uid'      => $gbx->MapUid,
                    'author'   => $authorId,
                    'filename' => $filename,
                    'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'enabled'  => 1,
                ]);
            }

            try {
                Server::addMap($map->filename);
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));
                Hook::fire('MapPoolUpdated');
            } catch (\Exception $e) {
                Log::warning("Map $map->filename already added.");
            }


            ChatController::message(onlinePlayers(), '_info', 'New map added: ', $map);
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

    public static function resetRound(Player $player)
    {
        Server::restartMap();
    }
}
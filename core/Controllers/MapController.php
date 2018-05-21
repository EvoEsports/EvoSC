<?php

namespace esc\Controllers;


use esc\Classes\Config;
use esc\Classes\Database;
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
        self::$mapsPath = Server::getMapsDirectory();

        Hook::add('PlayerConnect', 'MapController::displayMapWidget');
        Hook::add('BeginMap', 'MapController::beginMap');
        Hook::add('EndMatch', 'MapController::endMatch');

        ChatController::addCommand('skip', 'MapController::skip', 'Skips map instantly', '//', 'skip');
        ChatController::addCommand('settings', 'MapController::settings', 'Load match settings', '//', 'ban');
        ChatController::addCommand('add', 'MapController::addMap', 'Add a map from mx. Usage: //add \<mxid\>', '//', 'map.add');
        ChatController::addCommand('res', 'MapController::forceReplay', 'Queue map for replay', '//', 'map.replay');
        ChatController::addCommand('addtime', 'MapController::addTimeManually', 'Adds time (you can also substract)', '//', 'time');
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
    public static function endMatch($rankings = null, $winnerteam = null)
    {
        $request = self::$queue->shift();

        if ($request) {
            Log::info("Setting next map: " . $request->map->Name);
            Server::chooseNextMap($request->map->FileName);
            ChatController::messageAll("\$n\$fff", ' Next map is ', $request->map, ' as requested by ', $request->issuer);
        } else {
            $nextMap = self::getNext();
            ChatController::messageAll("\$n\$fff", ' Next map is ', $nextMap);
        }
    }

    /*
     * Hook: BeginMap
     */
    public static function beginMap(Map $map)
    {
        $map->update(Server::getCurrentMapInfo()->toArray());

        $map->increment('Plays');
        $map->update(['LastPlayed' => Carbon::now()]);

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
            Server::removeMap($map->FileName);
        } catch (FileException $e) {
            Log::error($e);
        }

        $deleted = File::delete(Config::get('server.maps') . '/' . $map->FileName);

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
            Server::removeMap($map->FileName);
        } catch (FileException $e) {
            Log::error($e);
        }

        $map->update(['Enabled' => false]);
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
            $map     = Map::where('UId', $mapInfo->uId)
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
                ->contains('map.UId', $currentMap->UId)) {
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
    public static function queueMap(Player $player, Map $map)
    {
        if (self::getQueue()
                ->where('player', $player)
                ->isNotEmpty() && !$player->isAdmin()) {
            ChatController::message($player, "You already have a map in queue", []);

            return;
        }

        self::$queue->push(new MapQueueItem($player, $map, time()));

        Server::chooseNextMap(self::getNext()->FileName);

        ChatController::messageAll($player, ' juked map ', $map);
        Log::info("$player->NickName juked map $map->Name");

        self::displayMapWidget();
    }

    /**
     * Loads maps from server directory
     */
    private static function loadMaps()
    {
        //Get loaded maps
        $maps = collect(Server::getMapList());

        //get array with the UIDs
        $enabledMapsUids = $maps->pluck('uId');

        foreach ($maps as $mapInfo) {
            $map = Map::where('UId', $mapInfo->uId)
                      ->get()
                      ->first();

            if (!$map) {
                //Map does not exist, create it
                $map = Map::create([
                    'UId'           => $mapInfo->uId,
                    'Name'          => $mapInfo->name,
                    'FileName'      => $mapInfo->fileName,
                    'Author'        => $mapInfo->author,
                    'AuthorTime'    => $mapInfo->authorTime,
                    'Mood'          => $mapInfo->mood,
                    'NbLaps'        => $mapInfo->nbLaps,
                    'NbCheckpoints' => $mapInfo->nbCheckpoints,
                    'Environnement' => $mapInfo->environnement,
                    'Enabled'       => true,
                ]);
            }

            //If file location has changed, update
            if ($map->FileName != $mapInfo->fileName) {
                $map->update(['FileName' => $mapInfo->fileName]);
                Log::logAddLine('MapController', 'Map moved ' . $map->FileName . ' .. ' . $mapInfo->fileName);
            }
        }

        //Disable maps
        Map::whereNotIn('UId', $enabledMapsUids)
           ->update(['Enabled' => false]);

        //Enable loaded maps
        Map::whereIn('UId', $enabledMapsUids)
           ->update(['Enabled' => true]);
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

        $result = RestClient::get('https://api.mania-exchange.com/tm/maps/' . $map->UId);

        if ($result->getStatusCode() != 200) {
            Log::logAddLine('MapController', 'Failed to fetch MX details: ' . $result->getReasonPhrase());
            return;
        }

        $map->update(['mx_details' => $result->getBody()->getContents()]);

        Log::logAddLine('MapController', 'Updated MX details for track: ' . $map->Name);

        $result = RestClient::get('https://api.mania-exchange.com/tm/tracks/worldrecord/' . $map->mx_details->TrackID);

        if ($result->getStatusCode() != 200) {
            Log::logAddLine('MapController', 'Failed to fetch MX world record: ' . $result->getReasonPhrase());
            return;
        }

        $map->update(['mx_world_record' => $result->getBody()->getContents()]);

        Log::logAddLine('MapController', 'Updated MX world record for track: ' . $map->Name);
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

            $map = Map::where('MxId', $mxId)
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

            $fileName = preg_replace('/^attachment; filename="(.+)"$/', '\1',
                $response->getHeader('content-disposition')[0]);

            $mapFolder = self::$mapsPath;
            File::put("$mapFolder/$fileName", $response->getBody());

            $map = Map::firstOrCreate([
                'MxId'     => $mxId,
                'FileName' => html_entity_decode($fileName, ENT_QUOTES | ENT_HTML5),
            ]);

            $info = Server::getMapInfo($map->FileName)
                          ->toArray();

            if ($info) {
                $map->update($info);
            }

            try {
                Server::addMap($map->FileName);
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));
            } catch (\Exception $e) {
                Log::warning("Map $map->FileName already added.");
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
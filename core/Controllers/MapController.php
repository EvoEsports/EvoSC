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
use esc\Models\Group;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\AlreadyInListException;
use Maniaplanet\DedicatedServer\Xmlrpc\FaultException;
use Maniaplanet\DedicatedServer\Xmlrpc\FileException;

class MapController
{
    private static $currentMap;
    private static $queue;
    private static $addedTime = 0;

    public static function init()
    {
        self::createTables();

        self::loadMaps();

        self::$queue = new Collection();

        Template::add('map', File::get('core/Templates/map.latte.xml'));

        Hook::add('PlayerConnect', '\esc\Controllers\MapController::displayMapWidget');
        Hook::add('BeginMap', 'esc\Controllers\MapController::beginMap');
        Hook::add('EndMatch', 'esc\Controllers\MapController::endMatch');

        ChatController::addCommand('skip', '\esc\Controllers\MapController::skip', 'Skips map instantly', '//', 'skip');
        ChatController::addCommand('add', '\esc\Controllers\MapController::addMap', 'Add a map from mx. Usage: //add \<mxid\>', '//', 'map.add');
    }

    public static function createTables()
    {
        Database::create('maps', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('UId')->nullable();
            $table->integer('MxId')->nullable();
            $table->string('Name')->nullable();
            $table->string('Author')->nullable();
            $table->string('FileName')->unique();
            $table->string('Environment')->nullable();
            $table->integer('NbCheckpoints')->nullable();
            $table->integer('NbLaps')->nullable();
            $table->integer('Plays')->default(0);
            $table->string('Mood')->nullable();
            $table->boolean('LapRace')->nullable();
            $table->dateTime('LastPlayed')->nullable();
            $table->boolean('Available')->default(false);
        });
    }

    /**
     * Reset time on round end for example
     */
    public static function resetTime()
    {
        self::$addedTime = 0;
        self::updateRoundtime(config('server.roundTime', 7) * 60);
    }

    /**
     * Add time to the counter
     * @param int $minutes
     */
    public static function addTime(int $minutes = 5)
    {
        self::$addedTime = self::$addedTime + $minutes;
        $totalNewTime = (config('server.roundTime', 7) + self::$addedTime) * 60;
        self::updateRoundtime($totalNewTime);
    }

    private static function updateRoundtime(int $timeInSeconds)
    {
        $settings = \esc\Classes\Server::getModeScriptSettings();
        $settings['S_TimeLimit'] = $timeInSeconds;
        \esc\Classes\Server::setModeScriptSettings($settings);
    }

    /**
     * Hook: EndMatch
     * @param $rankings
     * @param $winnerteam
     */
    public static function endMatch($rankings = null, $winnerteam = null)
    {
        $request = self::$queue->shift();

        if ($request) {
            Log::info("Try set next map: " . $request->map->Name);
            Server::chooseNextMap($request->map->FileName);
            ChatController::messageAll('Next map: ', $request->map, ' as requested by ', $request->issuer);
        } else {
            $nextMap = self::getNext();
            ChatController::messageAll('Next map: ', $nextMap);
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

        foreach (finishPlayers() as $player) {
            $player->setScore(0);
        }

        self::$currentMap = $map;
        self::displayMapWidget();
        PlayerController::displayPlayerlist();

        self::resetTime();
    }

    /**
     * Gets current map
     * @return Map|null
     */
    public static function getCurrentMap(): ?Map
    {
        return self::$currentMap;
    }

    /**
     * Get all queued maps
     * @return Collection
     */
    public static function getQueue(): Collection
    {
        return self::$queue->sortBy('timeRequested');
    }

    /**
     * Delete a map
     * @param Map $map
     */
    public static function deleteMap(Map $map)
    {
        try {
            Server::removeMap($map->FileName);
        } catch (FileException $e) {
            Log::error($e);
        }

        $deleted = File::delete(Config::get('server.maps') . '/' . $map->FileName);

        if ($deleted) {
            ChatController::messageAll('Admin removed map ', $map);
            $map->delete();
        }
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
     * @return Map
     */
    public static function getNext(): Map
    {
        $first = self::$queue->first();

        if ($first) {
            $map = self::$queue->first()->map;
        } else {
            $mapInfo = Server::getNextMapInfo();
            $map = Map::where('UId', $mapInfo->uId)->first();
        }

        return $map;
    }

    /**
     * Admins skip method
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
     * @param Player $player
     */
    public static function forceReplay(Player $player)
    {
        $currentMap = self::getCurrentMap();

        if (self::getQueue()->contains('map.UId', $currentMap->UId)) {
            ChatController::message($player, 'Map is already being replayed');
            return;
        }

        self::$queue->push(new MapQueueItem($player, $currentMap, 0));
        ChatController::messageAll($player, ' queued map ', $currentMap, ' for replay');
        self::displayMapWidget();
    }

    /**
     * Add a map to the queue
     * @param Player $player
     * @param Map $map
     */
    public static function queueMap(Player $player, Map $map)
    {
        if (self::getQueue()->where('player', $player)->isNotEmpty() && !$player->isAdmin()) {
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
        $mapFiles = File::getDirectoryContents(Config::get('server.maps'))->filter(function ($fileName) {
            return preg_match('/\.gbx$/i', $fileName);
        });

        foreach ($mapFiles as $mapFile) {
            $map = Map::where('FileName', $mapFile)->first();

            try {
                Server::addMap($mapFile);
            } catch (FileException $e) {
                Log::error("Map $mapFile not found.");
            } catch (AlreadyInListException $e) {
//                Log::warning("Map $mapFile already added.");
            }

            if (!$map) {
                if (preg_match('/^_(\d+)\.Map\.gbx$/', $mapFile, $matches)) {
                    $mxId = (int)$matches[1];
                }

                $mapInfo = Server::getMapInfo($mapFile)->toArray();
                $map = Map::create($mapInfo);

                if (isset($mxId)) {
                    $map->update(['MxId' => $mxId]);
                }
            }
        }
    }

    /**
     * Display the map widget
     * @param Player|null $player
     */
    public static function displayMapWidget(Player $player = null)
    {
        $currentMap = self::getCurrentMap();
        $nextMap = self::getNext();

        if ($player) {
            Template::show($player, 'map', ['map' => $currentMap, 'next' => $nextMap]);
        } else {
            Template::showAll('map', ['map' => $currentMap, 'next' => $nextMap]);
        }
    }

    public static function getMapInformationFromMx(Map $map): array
    {
        $result = RestClient::get('https://api.mania-exchange.com/tm/maps/' . $map->MxId);
        $i = json_decode($result->getBody()->getContents())[0];

        var_dump($i);

        $information = [
            'UId' => $i->TrackUID,
            'Name' => $i->GbxMapName
        ];

        return $information;
    }

    /**
     * Add map from MX
     * @param string[] ...$arguments
     */
    public static function addMap(string ...$arguments)
    {
        $mxIds = $arguments;

        //shift first two entries so we get list of mx ids
        array_shift($mxIds);
        array_shift($mxIds);

        foreach ($mxIds as $mxId) {
            $mxId = (int)$mxId;

            if ($mxId == 0) {
                Log::warning("Requested map with invalid id: " . $mxId);
                ChatController::messageAll("Requested map with invalid id: " . $mxId);
                return;
            }

            $map = Map::where('MxId', $mxId)->first();
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

            $fileName = preg_replace('/^attachment; filename="(.+)"$/', '\1', $response->getHeader('content-disposition')[0]);
            $mapFolder = Config::get('server.maps');
            File::put("$mapFolder/$fileName", $response->getBody());

            $map = Map::firstOrCreate([
                'MxId' => $mxId,
                'FileName' => $fileName
            ]);

            $info = Server::getMapInfo($map->FileName)->toArray();
            if ($info) {
                $map->update($info);
            }

            try {
                Server::addMap($map->FileName);
            } catch (\Exception $e) {
                Log::warning("Map $map->FileName already added.");
            }

            ChatController::messageAll('New map added: ', $map);
        }
    }
}
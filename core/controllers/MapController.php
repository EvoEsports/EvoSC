<?php

namespace esc\controllers;


use esc\classes\Config;
use esc\classes\Database;
use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\ManiaLinkEvent;
use esc\classes\RestClient;
use esc\classes\Template;
use esc\models\Map;
use esc\models\Player;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\AlreadyInListException;
use Maniaplanet\DedicatedServer\Xmlrpc\FaultException;

class MapController
{
    private static $currentMap;
    private static $queue;
    private static $nextMap;

    public static function initialize()
    {
        self::createTables();

        self::loadMaps();

        self::$queue = new Collection();

        Template::add('map', File::get('core/Templates/map.latte.xml'));

        Hook::add('BeginMap', '\esc\controllers\MapController::beginMap');
        Hook::add('BeginMap', '\esc\controllers\MapController::endMap');
        Hook::add('PlayerConnect', '\esc\controllers\MapController::displayMapWidget');

        ChatController::addCommand('add', '\esc\controllers\MapController::addMap', 'Add a map from mx. Usage: //add <mxid>', '//', ['Admin', 'SuperAdmin']);
    }

    private static function createTables()
    {
        Database::create('maps', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('UId')->nullable();
            $table->integer('MxId')->nullable();
            $table->string('Name')->nullable();
            $table->string('Author')->nullable();
            $table->string('FileName')->unique();
            $table->integer('Plays')->default(0);
            $table->string('Mood')->nullable();
            $table->boolean('LapRace')->nullable();
            $table->dateTime('LastPlayed')->nullable();
        });
    }

    public static function endMap(Map $map)
    {
        $request = self::getQueue()->shift();

        if (isset($request->map)) {
            self::setNext($request->map);
        }

        foreach (PlayerController::getPlayers() as $player) {
            $player->setScore(0);
        }
    }

    public static function beginMap(Map $map)
    {
        $map->update(ServerController::getCurrentMapInfo()->toArray());

        $map->increment('Plays');
        $map->update(['LastPlayed' => Carbon::now()]);

        self::$currentMap = $map;

        if (self::$nextMap && $map->FileName != self::$nextMap->FileName) {
            Log::warning("Skipping incompatible map " . self::$nextMap->Name);
            ChatController::messageAll("Skipping incompatible map " . self::$nextMap->Name);
        }

        $nextMap = Map::where('FileName', ServerController::getNextMapInfo()->fileName)->first();
        self::$nextMap = $nextMap;

        self::displayMapWidget();
    }

    public static function getCurrentMap(): ?Map
    {
        return self::$currentMap;
    }

    public static function getQueue(): Collection
    {
        return self::$queue;
    }

    public static function addMap(string ...$arguments)
    {
        $mxId = intval($arguments[2]);

        if ($mxId == 0) {
            Log::warning("Requested map with invalid id: " . $arguments[2]);
            ChatController::messageAll("Requested map with invalid id: " . $arguments[2]);
            return;
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

        $name = str_replace('.Map.Gbx', '', $fileName);

        $map = Map::updateOrCreate([
            'MxId' => $mxId,
            'Name' => $name,
            'FileName' => $fileName
        ]);

        ServerController::getRpc()->insertMap($map->FileName);

        ChatController::messageAll("Admin added map \$eee$name.");
    }

    public static function deleteMap(Map $map)
    {
        ServerController::getRpc()->removeMap($map->FileName);
        File::delete(Config::get('server.maps') . '/' . $map->FileName);
        ChatController::messageAll("Admin removed map \$eee$map->Name");
        $map->delete();
    }

    public static function setNext(Map $map = null)
    {
        ServerController::getRpc()->chooseNextMap($map->FileName);
        self::$nextMap = $map;
    }

    public static function next()
    {
        try {
            ServerController::getRpc()->nextMap();
        } catch (FaultException $e) {
            Log::error("$e");
        }
    }

    public static function getNext(): Map
    {
        $map = self::getQueue()->first()['map'];

        if (!$map) {
            $mapId = ServerController::getRpc()->getNextMapInfo()->uId;
            $map = Map::where('UId', $mapId)->first();
        }

        return $map;
    }

    public static function queueMap(Player $player, Map $map)
    {
        if (self::getQueue()->where('player', $player)->isNotEmpty()) {
            ChatController::message($player, "You already have a map in queue", []);
            return;
        }

        self::getQueue()->push([
            'player' => $player,
            'map' => $map
        ]);

        ChatController::messageAll('%s $z$s$%squeued map %s',
            $player->NickName,
            config('color.primary'), $map->Name);

        self::displayMapWidget();
    }

    private static function loadMaps()
    {
        $mapFiles = File::getDirectoryContents(Config::get('server.maps'))->filter(function ($fileName) {
            return preg_match('/^.+.Gbx$/', $fileName);
        });

        foreach ($mapFiles as $mapFile) {
            $map = Map::where('FileName', $mapFile)->first();
            if (!$map) {
                $mapInfo = ServerController::getMapInfo($mapFile)->toArray();
                Map::create($mapInfo);
            }

            try {
                ServerController::getRpc()->addMap($mapFile);
            } catch (AlreadyInListException $e) {
                Log::warning("Map $mapFile already added.");
            }
        }
    }

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
}
<?php

namespace esc\controllers;


use esc\classes\Config;
use esc\classes\Database;
use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\RestClient;
use esc\classes\Template;
use esc\models\Map;
use Maniaplanet\DedicatedServer\Xmlrpc\AlreadyInListException;
use Maniaplanet\DedicatedServer\Xmlrpc\FaultException;

class MapController
{
    private static $currentMap;
    private static $nextMap;

    public static function initialize()
    {
        self::createTables();

        self::loadMaps();

        Template::add('map', File::get('core/Templates/map.latte.xml'));

        Hook::add('BeginMap', '\esc\controllers\MapController::beginMap');
        Hook::add('BeginMap', '\esc\controllers\MapController::endMap');

        ChatController::addCommand('add', '\esc\controllers\MapController::addMap', 'Add a map from mx by it\'s id', '//');
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
        });
    }

    public static function endMap(Map $map)
    {
        foreach (PlayerController::getPlayers() as $player) {
            $player->setScore(0);
        }
    }

    public static function beginMap(Map $map)
    {
        $map->update(ServerController::getCurrentMapInfo()->toArray());
        $map->increment('Plays');
        self::$currentMap = $map;

        if (self::$nextMap && $map->FileName != self::$nextMap->FileName) {
            Log::warning("Skipping incompatible map " . self::$nextMap->Name);
            ChatController::messageAll("Skipping incompatible map " . self::$nextMap->Name);
        }

        $nextMap = Map::where('FileName', ServerController::getNextMapInfo()->fileName)->first();
        self::$nextMap = $nextMap;

        Template::showAll('map', ['map' => $map, 'next' => $nextMap]);
    }

    public static function getCurrentMap(): ?Map
    {
        return self::$currentMap;
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

    public static function setRandomNext()
    {
        $map = Map::all()->random();
        self::setNext($map);
    }

    public static function setNext(Map $map = null)
    {
        ServerController::getRpc()->chooseNextMap($map->FileName);
        self::$nextMap = $map;
        Template::showAll('map', ['map' => self::$currentMap, 'next' => $map]);
    }

    public static function next()
    {
        try {
            ServerController::getRpc()->nextMap();
        } catch (FaultException $e) {
            Log::error("$e");
        }
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
}
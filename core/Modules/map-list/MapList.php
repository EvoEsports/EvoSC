<?php

use esc\classes\File;
use esc\classes\ManiaLinkEvent;
use esc\classes\Template;
use esc\controllers\ChatController;
use esc\controllers\MapController;
use esc\models\Map;
use esc\models\Player;

class MapList
{
    public function __construct()
    {
        Template::add('maplist.show', File::get(__DIR__ . '/Templates/map-list.latte.xml'));

        ManiaLinkEvent::add('maplist.close', 'MapList::closeMapList');
        ManiaLinkEvent::add('maplist.queue', 'MapList::queueMap');
        ManiaLinkEvent::add('map.delete', 'MapList::deleteMap');

        ChatController::addCommand('maps', 'MapList::showMapList', 'Display list of maps');
    }

    public static function showMapList(Player $player)
    {
        $maps = Map::all();
        $queuedMaps = MapController::getQueue();

        Template::show($player, 'maplist.show', ['maps' => $maps, 'player' => $player, 'queuedMaps' => $queuedMaps]);
    }

    public static function closeMapList(Player $player)
    {
        Template::hide($player, 'maplist.show');
    }

    public static function queueMap(Player $player, $mapId)
    {
        $map = Map::where('id', intval($mapId))->first();

        if($map){
            MapController::queueMap($player, $map);
            Template::hide($player, 'maplist.show');
        }else{
            ChatController::message($player, 'Invalid map selected');
        }

        self::closeMapList($player);
    }

    public static function deleteMap(Player $player, $mapId)
    {
        $map = Map::where('id', intval($mapId))->first();
        MapController::deleteMap($map);
        self::showMapList($player);
    }
}
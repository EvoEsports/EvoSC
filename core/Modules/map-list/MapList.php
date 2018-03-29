<?php

use esc\Classes\File;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Map;
use esc\Models\Player;

class MapList
{
    public function __construct()
    {
        Template::add('maplist.show', File::get(__DIR__ . '/Templates/map-list.latte.xml'));

        ManiaLinkEvent::add('maplist.show', 'MapList::showMapList');
        ManiaLinkEvent::add('maplist.queue', 'MapList::queueMap');
        ManiaLinkEvent::add('maplist.filter.author', 'MapList::filterAuthor');
        ManiaLinkEvent::add('maplist.delete', 'MapList::deleteMap', 'map.delete');

        ChatController::addCommand('list', 'MapList::list', 'Display list of maps');
    }

    public static function list(Player $player, $cmd, $filter = null)
    {
        self::showMapList($player, 1, $filter);
    }

    public static function showMapList(Player $player, $page = 1, $filter = null)
    {
        $page = (int)$page;

        $perPage = 23;

        $allMaps = Map::all();

        if ($filter) {
            if ($filter == 'worst') {
                $worstLocals = $player->locals()->orderByDesc('Rank')->get();
                $allMaps = collect([]);
                foreach ($worstLocals as $local) {
                    $allMaps->push($local->map);
                }
            } elseif ($filter == 'best') {
                $worstLocals = $player->locals()->orderBy('Rank')->get();
                $allMaps = collect([]);
                foreach ($worstLocals as $local) {
                    $allMaps->push($local->map);
                }
            } elseif ($filter == 'nofinish') {
                $allMaps = $allMaps->filder(function (Map $map) use ($player) {
                    return (!$map->locals()->wherePlayer($player->id)->get()->first() && !$map->dedis()->wherePlayer($player->id)->get()->first());
                });
            } else {
                $allMaps = $allMaps->filter(function (Map $map) use ($filter) {
                    $nameMatch = strpos(strtolower(stripAll($map->Name)), strtolower($filter));
                    return (is_int($nameMatch) || $map->Author == $filter);
                });
            }
        }

        $pages = ceil($allMaps->count() / $perPage);

        $maps = $allMaps->forPage($page, $perPage);
        $queuedMaps = MapController::getQueue()->sortBy('timeRequested')->take($perPage);

        $mapList = Template::toString('maplist.show', ['maps' => $maps, 'player' => $player, 'queuedMaps' => $queuedMaps]);
        $pagination = Template::toString('esc.pagination', ['pages' => $pages, 'action' => 'maplist.show', 'page' => $page]);

        Template::show($player, 'esc.modal', [
            'id' => 'MapList',
            'width' => 180,
            'height' => 97,
            'content' => $mapList,
            'pagination' => $pagination
        ]);
    }

    public static function filterAuthor(Player $player, $authorLogin, $page = 1)
    {
        self::showMapList($player, $page, $authorLogin);
    }

    public static function closeMapList(Player $player)
    {
        Template::hide($player, 'MapList');
    }

    public static function queueMap(Player $player, $mapId)
    {
        $map = Map::where('id', intval($mapId))->first();

        if ($map) {
            MapController::queueMap($player, $map);
            Template::hide($player, 'maplist.show');
        } else {
            ChatController::message($player, 'Invalid map selected');
        }

        self::closeMapList($player);
    }

    public static function deleteMap(Player $player, $mapId)
    {
        if (!$player->isAdmin()) {
            ChatController::message($player, 'You do not have access to that command');
            return;
        }

        $map = Map::where('id', intval($mapId))->first();

        if ($map) {
            MapController::deleteMap($map);
            self::closeMapList($player);
        }
    }
}
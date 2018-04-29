<?php

namespace esc\Modules\MapList;

use esc\Classes\File;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MapList
{
    public function __construct()
    {
        Template::add('maplist.show', File::get(__DIR__ . '/Templates/map-list.latte.xml'));
        Template::add('maplist.details', File::get(__DIR__ . '/Templates/map-details.latte.xml'));

        ManiaLinkEvent::add('maplist.show', 'MapList::showMapList');
        ManiaLinkEvent::add('maplist.queue', 'MapList::queueMap');
        ManiaLinkEvent::add('maplist.filter', 'MapList::filter');
        ManiaLinkEvent::add('maplist.delete', 'MapList::deleteMap', 'map.delete');
        ManiaLinkEvent::add('maplist.disable', 'MapList::disableMap', 'map.delete');
        ManiaLinkEvent::add('maplist.details', 'MapList::showMapDetails');

        ChatController::addCommand('list', 'MapList::list', 'Display list of maps');
    }

    public static function list(Player $player, $cmd, $filter = null)
    {
        self::showMapList($player, 1, $filter);
    }

    private static function getRecordsForPlayer(Player $player): Collection
    {
        $records = collect([])
            ->concat($player->locals)
            ->concat($player->dedis);

        return $records;
    }

    private static function getRecordsForMapsAndPlayer($maps, Player $player): ?array
    {
        $mapIds = array_keys($maps);

        try {
            $records = [
                'locals' => LocalRecord::whereIn('Map', $mapIds)
                    ->wherePlayer($player->id)
                    ->get()
                    ->keyBy('Map')
                    ->all(),
                'dedis' => Dedi::whereIn('Map', $mapIds)
                    ->wherePlayer($player->id)
                    ->get()
                    ->keyBy('Map')
                    ->all(),
            ];
        } catch (\Exception $e) {
            \esc\Classes\Log::error('Failed to load records for player ' . $player->Login . "\n" . $e->getTrace());

            return null;
        }

        return $records;
    }

    public static function showMapList(Player $player, $page = null, $filter = null)
    {
        $perPage = 23;

        if ($filter) {
            if ($filter == 'worst') {

                $maps = self::getRecordsForPlayer($player)
                    ->sortByDesc('Rank')
                    ->pluck('map');

            } elseif ($filter == 'best') {

                $maps = self::getRecordsForPlayer($player)
                    ->sortBy('Rank')
                    ->pluck('map');

            } elseif ($filter == 'nofinish') {

                $records = self::getRecordsForPlayer($player)
                    ->pluck('map.id')
                    ->toArray();

                $maps = maps()->whereNotIn('id', $records);

            } else {

                $maps = maps()
                    ->filter(function (Map $map) use ($filter) {
                        $nameMatch = strpos(strtolower(stripAll($map->Name)), strtolower($filter));

                        return (is_int($nameMatch) || $map->Author == $filter);
                    });

            }
        } else {
            $maps = maps();
        }

        $pages = ceil(count($maps) / $perPage);

        $maps = $maps->forPage($page ?? 0, $perPage)
            ->keyBy('id')
            ->all();

        $records = self::getRecordsForMapsAndPlayer($maps, $player);

        $queuedMaps = MapController::getQueue()
            ->sortBy('timeRequested')
            ->take($perPage);

        $mapList = Template::toString('maplist.show', [
            'maps' => $maps,
            'player' => $player,
            'queuedMaps' => $queuedMaps,
            'filter' => $filter,
            'page' => $page,
            'locals' => $records['locals'],
            'dedis' => $records['dedis'],
        ]);

        $pagination = Template::toString('esc.pagination', [
            'pages' => $pages,
            'action' => $filter ? "maplist.filter,$filter" : 'maplist.show',
            'page' => $page,
        ]);

        Template::show($player, 'esc.modal', [
            'id' => 'MapList',
            'width' => 180,
            'height' => 97,
            'content' => $mapList,
            'pagination' => $pagination,
            'showAnimation' => isset($page) ? false : true,
        ]);
    }

    public static function filter(Player $player, $filter, $page = 1)
    {
        self::showMapList($player, $page, $filter);
    }

    public static function closeMapList(Player $player)
    {
        Template::hide($player, 'MapList');
    }

    public static function queueMap(Player $player, $mapId)
    {
        $map = Map::where('id', intval($mapId))
            ->first();

        if ($map) {
            MapController::queueMap($player, $map);
            Template::hide($player, 'maplist.show');
        } else {
            ChatController::message($player, 'Invalid map selected');
        }

        self::closeMapList($player);
    }

    public static function disableMap(Player $player, $mapId)
    {
        $map = Map::where('id', intval($mapId))->first();

        if ($map) {
            MapController::disableMap($player, $map);
            self::closeMapList($player);
        }
    }

    public static function deleteMap(Player $player, $mapId)
    {
        $map = Map::where('id', intval($mapId))->first();

        if ($map) {
            MapController::deleteMap($player, $map);
            self::closeMapList($player);
        }
    }

    public static function showMapDetails(Player $player, $mapId, $page = 1, $filter = '', $returnToMaplist = false)
    {
        $map = Map::find($mapId);

        $locals = $map->locals()->orderBy('Score')->get()->take(5);
        $dedis = $map->dedis()->orderBy('Score')->get()->take(5);

        $localsRanking = Template::toString('esc.ranking', ['ranks' => $locals]);
        $dedisRanking = Template::toString('esc.ranking', ['ranks' => $dedis]);

        $detailPage = Template::toString('maplist.details', compact('map', 'localsRanking', 'dedisRanking'));

        Template::show($player, 'esc.modal', [
            'id' => 'MapList',
            'title' => 'Map details: ' . $map->Name,
            'width' => 120,
            'height' => 50,
            'content' => $detailPage,
            'onClose' => (strlen($filter) > 0 || $returnToMaplist) ? "maplist.filter,$filter,$page" : 'modal.hide,MapList',
            'showAnimation' => true,
        ]);
    }
}
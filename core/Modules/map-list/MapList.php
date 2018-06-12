<?php

namespace esc\Modules\MapList;

use Carbon\Carbon;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\MapQueueItem;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Dedi;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MapList
{
    public function __construct()
    {
        ManiaLinkEvent::add('maplist.show', [MapList::class, 'showMapList']);
        ManiaLinkEvent::add('maplist.queue', [MapList::class, 'queueMap']);
        ManiaLinkEvent::add('maplist.filter', [MapList::class, 'filter']);
        ManiaLinkEvent::add('maplist.delete', [MapList::class, 'deleteMap'], 'map.delete');
        ManiaLinkEvent::add('maplist.disable', [MapList::class, 'disableMap'], 'map.delete');
        ManiaLinkEvent::add('maplist.details', [MapList::class, 'showMapDetails']);

        ManiaLinkEvent::add('maplist.mx', [MapList::class, 'updateMxDetails']);

        ChatController::addCommand('list', [MapList::class, 'list'], 'Display list of maps');

        Hook::add('QueueUpdated', [MapList::class, 'mapQueueUpdated']);

        KeyController::createBind('X', [MapList::class, 'reload']);;

        ManiaLinkEvent::add('map.fav.add', [MapList::class, 'favAdd']);
        ManiaLinkEvent::add('map.fav.remove', [MapList::class, 'favRemove']);
    }

    public static function reload(Player $player)
    {
//        TemplateController::loadTemplates();
        self::showNewsMapList($player);
    }

    public static function favAdd(Player $player, int $mapId)
    {
        $player->favorites()->attach($mapId);
    }

    public static function favRemove(Player $player, int $mapId)
    {
        $player->favorites()->detach($mapId);
    }

    public static function getMapsCount()
    {
        return Map::count();
    }

    public static function mapQueueUpdated(Collection $queue)
    {
        onlinePlayers()->each(function (Player $player) use ($queue) {
            Template::show($player, 'map-list.update-queue', []);
        });
    }

    public static function mapQueueToManiaScriptArray()
    {
        return MapController::getQueue()->map(function (MapQueueItem $item) {
            return sprintf('["%s", "%s", "%s", "%s"]',
                $item->map->id,
                $item->map->MxId,
                $item->map->Name,
                $item->issuer->NickName
            );
        })->implode(",\n");
    }

    public static function updateMxDetails(Player $player, $mapId)
    {
        $map = Map::whereId($mapId)->first();

        if (!$map->mx_details) {
            MapController::loadMxDetails($map);
            $map = Map::whereId($mapId)->first();
        }

        $details = $map->mx_details;

        if (!$details) {
            ChatController::message($player, '_warning', 'Could not load mx details for track ', $map);
            return;
        }

        $mxDetails = sprintf('["%s", "%s", "%s", "%s", "%s", "%s", "%s"]',
            $map->id,
            $map->gbx->MapUid,
            $map->author->Login,
            $map->author->Login == $map->author->NickName ? $details->Username : $map->author->NickName,
            (new Carbon($details->UploadedAt))->format('Y-m-d'),
            (new Carbon($details->UpdatedAt))->format('Y-m-d'),
            $map->gbx->Name
        );

        Template::show($player, 'map-list.update-mx-details', [
            'mx_details' => $mxDetails
        ]);
    }

    public static function mapsToManiaScriptArray(Player $player)
    {
        $locals    = $player->locals->pluck('Rank', 'Map');
        $dedis     = $player->dedis->pluck('Rank', 'Map');
        $favorites = $player->favorites()->get(['id', 'gbx->Name as Name'])->pluck('Name', 'id');

        $maps = Map::all()->map(function (Map $map) use ($locals, $dedis, $favorites) {
            $author = $map->author;

            $authorLogin = $author->Login ?? "n/a";
            $authorNick  = stripAll($author->NickName ?? "n/a");

            $local    = $locals->get($map->id) ?: '-';
            $dedi     = $dedis->get($map->id) ?: '-';
            $favorite = $favorites->get($map->id) ? 1 : 0;
            $mapName  = $map->gbx->Name;

            $search = strtolower(stripAll($mapName) . $authorNick . $authorLogin);
            return sprintf('["%s","%s", "%s", "%s", "%s", "%s", "%s", "%s"]', $mapName, $authorNick, $authorLogin, $local, $dedi, $map->id, $favorite, $search);
        })->implode("\n,");

        return sprintf('[%s]', $maps);
    }

    public static function showNewsMapList(Player $player)
    {
        Template::show($player, 'map-list.manialink');
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
                'dedis'  => Dedi::whereIn('Map', $mapIds)
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
        $page    = intval($page);

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
                        $nameMatch = strpos(strtolower(stripAll($map->gbx->Name)), strtolower($filter));

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

        $mapList = Template::toString('map-list.map-list', [
            'maps'       => $maps,
            'player'     => $player,
            'queuedMaps' => $queuedMaps,
            'filter'     => $filter,
            'page'       => $page,
            'locals'     => $records['locals'],
            'dedis'      => $records['dedis'],
        ]);

        $pagination = Template::toString('components.pagination', [
            'pages'  => $pages,
            'action' => $filter ? "maplist.filter,$filter" : 'maplist.show',
            'page'   => $page,
        ]);

        Template::show($player, 'components.modal', [
            'id'            => 'MapList',
            'width'         => 180,
            'height'        => 97,
            'content'       => $mapList,
            'pagination'    => $pagination,
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
        $map = Map::whereId($mapId)->first();

        if ($map) {
            MapController::queueMap($player, $map);
            Template::hide($player, 'map-list.map-list');
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

        $locals = $map->locals()->orderBy('Score')->get()->take(10);
        $dedis  = $map->dedis()->orderBy('Score')->get()->take(10);

        $localsRanking = Template::toString('components.ranking', ['ranks' => $locals]);
        $dedisRanking  = Template::toString('components.ranking', ['ranks' => $dedis]);

        MapController::loadMxDetails($map);

        $mxDetails = $map->mx_details;

        if (!$mxDetails) {
            ChatController::message($player, '_warning', 'Could not load mx details for track ', $map);
            return;
        }

        $detailPage = Template::toString('map-list.map-details', compact('map', 'localsRanking', 'dedisRanking', 'mxDetails'));

        Template::show($player, 'components.modal', [
            'id'            => 'MapList',
            'title'         => 'Map details: ' . $map->gbx->Name,
            'width'         => 130,
            'height'        => 50,
            'content'       => $detailPage,
            'onClose'       => (strlen($filter) > 0 || $returnToMaplist) ? "maplist.filter,$filter,$page" : 'modal.hide,MapList',
            'showAnimation' => true,
        ]);
    }
}
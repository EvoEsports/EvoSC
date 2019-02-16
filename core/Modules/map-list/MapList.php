<?php

namespace esc\Modules\MapList;

use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\MapQueueItem;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MapList
{
    public function __construct()
    {
        ManiaLinkEvent::add('maplist.delete', [MapList::class, 'deleteMap'], 'map.delete');
        ManiaLinkEvent::add('maplist.delete-perm', [MapList::class, 'deleteMapPerm'], 'map.delete-perm');
        ManiaLinkEvent::add('map.queue', [MapList::class, 'queueMap']);
        ManiaLinkEvent::add('map.drop', [MapList::class, 'queueDropMap']);
        ManiaLinkEvent::add('map.fav.add', [MapList::class, 'favAdd']);
        ManiaLinkEvent::add('map.fav.remove', [MapList::class, 'favRemove']);

        Hook::add('MapPoolUpdated', [MapList::class, 'sendUpdatedMaplist']);
        Hook::add('QueueUpdated', [MapList::class, 'mapQueueUpdated']);
        Hook::add('PlayerConnect', [MapList::class, 'playerConnect']);
        Hook::add('GroupChanged', [self::class, 'sendManialink']);

        ChatController::addCommand('list', [self::class, 'searchMap'], 'Search maps or open maplist');
    }

    /**
     * Send manialink to player
     *
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        $mapQueue = self::getMapQueueJson();
        Template::show($player, 'map-list.update-map-queue', compact('mapQueue'));
        self::sendRecordsJson($player);
        self::sendManialink($player);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::sendManialink($player);
    }

    private static function getMapFavoritesJson(Player $player): string
    {
        return $player->favorites->pluck('id')->toJson();
    }


    public static function sendRecordsJson(Player $player)
    {
        $locals = $player->locals()->pluck('Rank', 'Map')->toJson();
        $dedis  = $player->dedis()->pluck('Rank', 'Map')->toJson();

        Template::show($player, 'map-list.update-records', compact('locals', 'dedis'));
    }

    public static function searchMap(Player $player, $cmd, $query = "")
    {
        Template::show($player, 'map-list.update-search-query', compact('query'));
    }

    /**
     * Player add favorite map
     *
     * @param Player $player
     * @param int    $mapId
     */
    public static function favAdd(Player $player, string $mapId)
    {
        $player->favorites()->attach($mapId);
    }

    /**
     * Player remove favorite map
     *
     * @param Player $player
     * @param int    $mapId
     */
    public static function favRemove(Player $player, string $mapId)
    {
        $player->favorites()->detach($mapId);
    }

    public static function queueDropMap(Player $player, $mapUid)
    {
        $map       = Map::whereUid($mapUid)->first();
        $queueItem = MapController::getQueue()->where('map', $map)->first();

        if (!$queueItem) {
            return;
        }

        if ($queueItem->issuer->Login != $player->Login) {
            ChatController::message($player, '_warning', 'You can not drop other players maps');

            return;
        }

        MapController::unqueueMap($map);
        self::mapQueueUpdated();
        ChatController::message(onlinePlayers(), $player, ' drops ', $map, ' from queue');
    }

    /**
     * Returns enabled maps count
     *
     * @return mixed
     */
    public static function getMapsCount()
    {
        return Map::whereEnabled(true)->count();
    }

    public static function sendUpdatedMaplist(Player $player = null)
    {
        $maps       = self::getMapList();
        $mapAuthors = self::getMapAuthors($maps->pluck('a'))->keyBy('id');

        if (strlen($maps->toJson()) > 65000) {
            Log::error('The map list json is too long! You have too many maps. Sorry, we are working on this.');

            return;
        }

        if ($player) {
            Template::show($player, 'map-list.update-map-list', [
                'maps'       => $maps->toJson(),
                'mapAuthors' => $mapAuthors->toJson(),
            ]);
        } else {
            Template::showAll('map-list.update-map-list', [
                'maps'       => $maps->toJson(),
                'mapAuthors' => $mapAuthors->toJson(),
            ]);
        }
    }

    private static function getMapList(): Collection
    {
        //max length ~65762
        //length 60088 is ok

        return Map::whereEnabled(true)->get()->map(function (Map $map) {
            return [
                'id'   => (string)$map->id,
                'name' => $map->gbx->Name,
                'a'    => $map->author->id,
                'r'    => sprintf('%.1f', $map->average_rating),
                'uid'  => $map->gbx->MapUid,
                'c'    => $map->cooldown,
            ];
        });
    }

    private static function getMapAuthors($authorIds): Collection
    {
        return Player::whereIn('id', $authorIds)->get()->map(function (Player $player) {
            return [
                'nick'  => $player->NickName,
                'login' => $player->Login,
                'id'    => $player->id,
            ];
        });
    }

    public static function deleteMap(Player $player, $mapUid)
    {
        $map = Map::whereUid($mapUid)->first();

        if (!$map) {
            return;
        }

        MapController::disableMap($player, $map);
    }

    public static function deleteMapPerm(Player $player, $mapUid)
    {
        //TODO: delete map permanently
        self::deleteMap($player, $mapUid);
    }

    /**
     * Send updated map queue to everyone
     *
     * @param Collection $queue
     */
    public static function mapQueueUpdated()
    {
        $mapQueue = self::getMapQueueJson();
        Template::showAll('map-list.update-map-queue', compact('mapQueue'));
    }

    private static function getMapQueueJson(): string
    {
        return MapController::getQueue()->map(function (MapQueueItem $item) {
            return [
                'id' => '' . $item->map->id,
                'by' => $item->issuer->Login,
            ];
        })->toJson();
    }

    /**
     * Display maplist
     *
     * @param Player $player
     */
    public static function sendManialink(Player $player)
    {
        self::sendUpdatedMaplist($player);
        $favorites      = self::getMapFavoritesJson($player);
        $ignoreCooldown = $player->hasAccess('queue.recent');
        Template::show($player, 'map-list.manialink', compact('favorites', 'ignoreCooldown'));
    }

    public static function queueMap(Player $player, $mapUid)
    {
        $map = Map::whereUid($mapUid)->first();

        if ($map) {
            MapController::queueMap($player, $map);
        }
    }
}
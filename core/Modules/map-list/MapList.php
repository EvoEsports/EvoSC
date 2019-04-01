<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Classes\ChatCommand;
use esc\Controllers\MapController;
use esc\Controllers\QueueController;
use esc\Controllers\TemplateController;
use esc\Models\Map;
use esc\Models\MapQueue;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MapList
{
    public function __construct()
    {
        ManiaLinkEvent::add('maplist.delete', [MapList::class, 'deleteMap'], 'map.delete');
        ManiaLinkEvent::add('maplist.delete-perm', [MapList::class, 'deleteMapPerm'], 'map.delete-perm');
        ManiaLinkEvent::add('map.fav.add', [MapList::class, 'favAdd']);
        ManiaLinkEvent::add('map.fav.remove', [MapList::class, 'favRemove']);

        Hook::add('MapPoolUpdated', [MapList::class, 'sendUpdatedMaplist']);
        Hook::add('MapQueueUpdated', [MapList::class, 'mapQueueUpdated']);
        Hook::add('PlayerConnect', [MapList::class, 'playerConnect']);
        Hook::add('GroupChanged', [self::class, 'sendManialink']);
        Hook::add('BeginMap', [self::class, 'beginMap']);

        ChatCommand::add('/maps', [self::class, 'searchMap'], 'Open map-list/favorites/queue.')
                   ->addAlias('/list');

        KeyBinds::add('reload_map_list', 'Reloads maplist', [self::class, 'reload'], 'X');
    }

    public static function mapMapQueue(MapQueue $item)
    {
        return [
            'queue_id' => $item->id,
            'id'       => $item->map->id,
            'by'       => $item->requesting_player,
        ];
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::playerConnect($player);
    }

    /**
     * Send manialink to player
     *
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        $mapQueue = QueueController::getMapQueue()->map([self::class, 'mapMapQueue']);
        Template::show($player, 'map-list.update-map-queue', compact('mapQueue'));
        self::sendRecordsJson($player);
        self::sendManialink($player);
    }

    private static function getMapFavoritesJson(Player $player): string
    {
        return $player->favorites->pluck('id')->toJson();
    }

    public static function beginMap(Map $map)
    {
        $map->update([
            'cooldown'    => 0,
            'last_played' => now(),
        ]);

        self::sendUpdatedMaplist();
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

        QueueController::dropMap($player, $map->uid);
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
     * @param \Illuminate\Support\Collection $queueItems
     */
    public static function mapQueueUpdated(Collection $queueItems)
    {
        $mapQueue = $queueItems->map([self::class, 'mapMapQueue']);
        Template::showAll('map-list.update-map-queue', compact('mapQueue'));
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
}
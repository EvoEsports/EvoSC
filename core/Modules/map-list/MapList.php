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
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
use esc\Models\MapQueue;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MapList implements ModuleInterface
{
    public static function mapMapQueue(MapQueue $item)
    {
        return [
            'queue_id' => $item->id,
            'id' => $item->map->id,
            'by' => $item->requesting_player,
            'nick' => player($item->requesting_player)->NickName,
        ];
    }

    /**
     * Send manialink to player
     *
     * @param  Player  $player
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
        return $player->favorites()->where('enabled', true)->pluck('id')->toJson();
    }

    public static function beginMap(Map $map)
    {
        self::sendUpdatedMaplist();
    }

    public static function sendRecordsJson(Player $player)
    {
        $locals = $player->locals()->orderBy('Rank')->pluck('Rank', 'Map')->toJson();
        $dedis = $player->dedis()->orderBy('Rank')->pluck('Rank', 'Map')->toJson();

        Template::show($player, 'map-list.update-records', compact('locals', 'dedis'));
    }

    public static function searchMap(Player $player, $cmd, $query = "")
    {
        Template::show($player, 'map-list.update-search-query', compact('query'));
    }

    /**
     * Player add favorite map
     *
     * @param  Player  $player
     * @param  string  $mapId
     */
    public static function favAdd(Player $player, string $mapId)
    {
        $player->favorites()->attach($mapId);
    }

    /**
     * Player remove favorite map
     *
     * @param  Player  $player
     * @param  string  $mapId
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
        $maps = self::getMapList();
        $mapAuthors = self::getMapAuthors($maps->pluck('a'))->keyBy('id');

        if (isVerbose()) {
            var_dump("Maps count: ".$maps->count());
            var_dump("Author IDs: ".$maps->pluck('a')->implode(', '));
            var_dump("Author Logins: ".$mapAuthors->pluck('Login')->implode(', '));
        }

        if ($player) {
            Template::show($player, 'map-list.update-map-list', [
                'maps' => $maps->chunk(100),
                'mapAuthors' => $mapAuthors->toJson(),
            ]);
        } else {
            Template::showAll('map-list.update-map-list', [
                'maps' => $maps->chunk(100),
                'mapAuthors' => $mapAuthors->toJson(),
            ]);
        }
    }

    private static function getMapList(): Collection
    {
        return Map::whereEnabled(1)->get()->transform(function (Map $map) {
            if (!$map->id || !$map->gbx->MapUid) {
                return null;
            }

            return [
                'id' => (string) $map->id,
                'name' => $map->name,
                'a' => $map->author->id,
                'r' => sprintf('%.1f', $map->average_rating),
                'uid' => $map->gbx->MapUid,
                'c' => $map->cooldown,
            ];
        })->filter();
    }

    private static function getMapAuthors($authorIds): Collection
    {
        return Player::whereIn('id', $authorIds)->get()->transform(function (Player $player) {
            return [
                'nick' => $player->NickName,
                'login' => $player->Login,
                'id' => $player->id,
            ];
        });
    }

    public static function disableMapEvent(Player $player, $mapUid)
    {
        $map = Map::whereUid($mapUid)->get()->last();

        if (!$map) {
            return;
        }

        QueueController::dropMap($player, $map->uid);
        MapController::disableMap($player, $map);
    }

    public static function deleteMapPermEvent(Player $player, $mapUid)
    {
        $map = Map::whereUid($mapUid)->get()->last();

        if (!$map) {
            return;
        }

        QueueController::dropMap($player, $map->uid);
        MapController::deleteMap($player, $map);
    }

    /**
     * Send updated map queue to everyone
     *
     * @param  \Illuminate\Support\Collection  $queueItems
     */
    public static function mapQueueUpdated(Collection $queueItems)
    {
        $mapQueue = $queueItems->map([self::class, 'mapMapQueue'])->filter();
        Template::showAll('map-list.update-map-queue', compact('mapQueue'));
    }

    /**
     * Display maplist
     *
     * @param  Player  $player
     */
    public static function sendManialink(Player $player)
    {
        self::sendUpdatedMaplist($player);
        $favorites = self::getMapFavoritesJson($player);
        $ignoreCooldown = $player->hasAccess('queue.recent');
        Template::show($player, 'map-list.map-list', compact('favorites', 'ignoreCooldown'));
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function start(string $mode)
    {
        ManiaLinkEvent::add('maplist.disable', [self::class, 'disableMapEvent'], 'map_disable');
        ManiaLinkEvent::add('maplist.delete', [self::class, 'deleteMapPermEvent'], 'map_delete');
        ManiaLinkEvent::add('map.fav.add', [self::class, 'favAdd']);
        ManiaLinkEvent::add('map.fav.remove', [self::class, 'favRemove']);

        Hook::add('MapPoolUpdated', [self::class, 'sendUpdatedMaplist']);
        Hook::add('MapQueueUpdated', [self::class, 'mapQueueUpdated']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('GroupChanged', [self::class, 'sendManialink']);
        Hook::add('BeginMap', [self::class, 'beginMap']);

        ChatCommand::add('/maps', [self::class, 'searchMap'], 'Open map-list/favorites/queue.')
            ->addAlias('/list');
    }
}
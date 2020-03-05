<?php

namespace esc\Modules;

use esc\Classes\ChatCommand;
use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Controllers\QueueController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
use esc\Models\MapQueue;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MapList extends Module implements ModuleInterface
{
    public static function playerConnect(Player $player)
    {
        self::sendFavorites($player);
        self::sendUpdatedMaplist($player);
        self::mapQueueUpdated(MapQueue::all());
        self::sendRecordsJson($player);
        Template::show($player, 'map-list.map-queue');
        Template::show($player, 'map-list.map-widget');
        Template::show($player, 'map-list.map-list');
    }

    public static function sendFavorites(Player $player)
    {
        $favorites = $player->favorites()->where('enabled', true)->pluck('id')->toJson();

        Template::show($player, 'map-list.update-favorites', compact('favorites'));
    }

    public static function sendRecordsJson(Player $player)
    {
        $locals = DB::table(LocalRecords::TABLE)->where('Player', '=', $player->id)->orderBy('Rank')->pluck('Rank', 'Map')->toJson();
        $dedis = DB::table(Dedimania::TABLE)->where('Player', '=', $player->id)->orderBy('Rank')->pluck('Rank', 'Map')->toJson();

        Template::show($player, 'map-list.update-records', compact('locals', 'dedis'));
    }

    public static function searchMap(Player $player, $query = "")
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
        self::sendFavorites($player);
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
        self::sendFavorites($player);
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
            var_dump("All maps count: ".Map::count());
            var_dump("Enabled maps count: ".Map::whereEnabled(1)->count());
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
     * @param Collection $queueItems
     */
    public static function mapQueueUpdated(Collection $queueItems)
    {
        $mapQueue = $queueItems->map(function (MapQueue $item) {
            return [
                'id' => $item->map->id,
                'uid' => $item->map->uid,
                'name' => $item->map->name,
                'author' => $item->map->author->NickName,
                'login' => $item->player->Login,
                'nick' => $item->player->NickName,
            ];
        })->filter();

        Template::showAll('map-list.update-map-queue', compact('mapQueue'));
    }

    public static function showMapQueue(Player $player)
    {
        Template::show($player, 'map-list.show-queue', null, false);
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ManiaLinkEvent::add('maplist.show_queue', [self::class, 'showQueue']);
        ManiaLinkEvent::add('maplist.disable', [self::class, 'disableMapEvent'], 'map_disable');
        ManiaLinkEvent::add('maplist.delete', [self::class, 'deleteMapPermEvent'], 'map_delete');
        ManiaLinkEvent::add('map.fav.add', [self::class, 'favAdd']);
        ManiaLinkEvent::add('map.fav.remove', [self::class, 'favRemove']);

        Hook::add('MapPoolUpdated', [self::class, 'sendUpdatedMaplist']);
        Hook::add('MapQueueUpdated', [self::class, 'mapQueueUpdated']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('GroupChanged', [self::class, 'playerConnect']);

        ChatCommand::add('/maps', [self::class, 'searchMap'], 'Open map-list.')
            ->addAlias('/list');
        ChatCommand::add('/jukebox', [self::class, 'showMapQueue'], 'Open jukebox/map-queue.')
            ->addAlias('/queue')
            ->addAlias('/jb');
    }
}
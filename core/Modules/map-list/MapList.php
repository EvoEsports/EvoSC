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
use Illuminate\Support\Facades\DB;

class MapList
{
    public function __construct()
    {
        ManiaLinkEvent::add('map.queue', [MapList::class, 'queueMap']);
        ManiaLinkEvent::add('map.fav.add', [MapList::class, 'favAdd']);
        ManiaLinkEvent::add('map.fav.remove', [MapList::class, 'favRemove']);

        Hook::add('QueueUpdated', [MapList::class, 'mapQueueUpdated']);
        Hook::add('PlayerConnect', [MapList::class, 'playerConnect']);

        ChatController::addCommand('list', [self::class, 'searchMap'], 'Search maps or open maplist');

        KeyController::createBind('X', [MapList::class, 'reload']);;
    }

    /**
     * Send manialink to player
     *
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        self::sendManialink($player);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::sendManialink($player);
    }

    public static function searchMap(Player $player, $cmd, $query = "")
    {
        Template::show($player, 'map-list.update-search-query', compact('query'));
    }

    /**
     * Player add favorite map
     *
     * @param Player $player
     * @param int $mapId
     */
    public static function favAdd(Player $player, int $mapId)
    {
        $player->favorites()->attach($mapId);
    }

    /**
     * Player remove favorite map
     *
     * @param Player $player
     * @param int $mapId
     */
    public static function favRemove(Player $player, int $mapId)
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

    /**
     * Send updated map queue to everyone
     *
     * @param Collection $queue
     */
    public static function mapQueueUpdated(Collection $queue)
    {
        $queue = $queue->take(7)->map(function (MapQueueItem $item) {
            return sprintf('["%s", "%s", "%s", "%s"]',
                $item->map->id,
                $item->map->gbx->MapUid,
                $item->map->gbx->Name,
                $item->issuer->NickName
            );
        })->implode(",");

        onlinePlayers()->each(function (Player $player) use ($queue) {
            Template::show($player, 'map-list.update-queue', compact('queue'));
        });
    }

    /**
     * Returns maps as MS array
     *
     * @param Player $player
     * @return string
     */
    public static function mapsToManiaScriptArray(Player $player)
    {
        $locals = $player->locals->pluck('Rank', 'Map');
        $dedis  = $player->dedis->pluck('Rank', 'Map');

        if (config('database.type') == 'mysql') {
            $favorites = $player->favorites()->get(['id', 'gbx->Name as Name'])->pluck('Name', 'id');
        } else {
            $favorites = $player->favorites()->get()->map(function (Map $map) {
                return ['id' => $map->id, 'Name' => $map->gbx->Name];
            })->pluck('Name', 'id');
        }

        $maps = Map::whereEnabled(true)->get()->map(function (Map $map) use ($locals, $dedis, $favorites) {
            $author = $map->author;

            $authorLogin = $author->Login ?? "n/a";
            $authorNick  = stripAll($author->NickName ?? "n/a");

            $local    = $locals->get($map->id) ?: '-';
            $dedi     = $dedis->get($map->id) ?: '-';
            $favorite = $favorites->get($map->id) ? 1 : 0;
            $mapName  = $map->gbx->Name;

            return sprintf('["%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s"]', $mapName, $authorNick, $authorLogin, $local, $dedi, $map->id, $favorite, $map->uid);
        })->implode("\n,");

        return sprintf('[%s]', $maps);
    }

    /**
     * Display maplist
     *
     * @param Player $player
     */
    public static function sendManialink(Player $player)
    {
        $maps = self::mapsToManiaScriptArray($player);

        Template::show($player, 'map-list.manialink', compact('maps'));
    }

    public static function queueMap(Player $player, $mapId)
    {
        $map = Map::whereId($mapId)->first();

        if ($map) {
            $queue = MapController::queueMap($player, $map);
            self::mapQueueUpdated($queue);
        }
    }
}
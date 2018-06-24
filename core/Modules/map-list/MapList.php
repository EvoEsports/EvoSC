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
        ManiaLinkEvent::add('map.mx', [MapList::class, 'updateMxDetails']);
        ManiaLinkEvent::add('map.queue', [MapList::class, 'queueMap']);
        ManiaLinkEvent::add('map.fav.add', [MapList::class, 'favAdd']);
        ManiaLinkEvent::add('map.fav.remove', [MapList::class, 'favRemove']);

        Hook::add('QueueUpdated', [MapList::class, 'mapQueueUpdated']);
        Hook::add('BeginMap', [MapList::class, 'beginMap']);
        Hook::add('PlayerConnect', [MapList::class, 'playerConnect']);

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
     * Send mx details of current map to everyone
     *
     * @param Map $map
     */
    public static function beginMap(Map $map)
    {
        onlinePlayers()->each(function (Player $player) use ($map) {
            MapList::updateMxDetails($player, $map->id);
        });
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
     * Sends requested mx details to player
     *
     * @param Player $player
     * @param $mapId
     */
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

        Log::logAddLine('MapList::updateMxDetails', json_encode($map->gbx));
        Log::logAddLine('MapList::updateMxDetails', json_encode($map->mx_details));

        $mxDetails = sprintf('["%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s"]',
            $map->id,
            $map->gbx->MapUid,
            $map->author->Login,
            $map->author->Login == $map->author->NickName ? $details->Username : $map->author->NickName,
            (new Carbon($details->UploadedAt))->format('Y-m-d'),
            (new Carbon($details->UpdatedAt))->format('Y-m-d'),
            $map->gbx->Name,
            $details->TrackID,
            formatScore($map->gbx->AuthorTime),
            $details->TitlePack,
            $details->Mood,
            $details->StyleName,
            $details->DifficultyName
        );

        Template::show($player, 'map-list.update-mx-details', [
            'mx_details' => $mxDetails
        ]);
    }

    /**
     * Returns maps as MS array
     *
     * @param Player $player
     * @return string
     */
    public static function mapsToManiaScriptArray(Player $player)
    {
        $locals    = $player->locals->pluck('Rank', 'Map');
        $dedis     = $player->dedis->pluck('Rank', 'Map');
        $favorites = $player->favorites()->get(['id', 'gbx->Name as Name'])->pluck('Name', 'id');

        $maps = Map::whereEnabled(true)->get()->map(function (Map $map) use ($locals, $dedis, $favorites) {
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
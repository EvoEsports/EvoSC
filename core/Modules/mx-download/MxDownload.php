<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\MxMap;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Classes\ChatCommand;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Controllers\MatchSettingsController;
use esc\Controllers\QueueController;
use esc\Models\Dedi;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\MapFavorite;
use esc\Models\MapQueue;
use esc\Models\Player;

class MxDownload
{
    public function __construct()
    {
        ChatCommand::add('//add', [self::class, 'addMap'], 'Add a map from mx. Usage: //add <mx_id>', 'map_add');
    }

    /**
     * @param \esc\Models\Player $player
     * @param                    $cmd
     * @param string             ...$arguments
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Throwable
     */
    public static function addMap(Player $player, $cmd, string ...$arguments)
    {
        foreach ($arguments as $mxId) {
            try {
                $mxMap = MxMap::get($mxId);
                $mxMap->loadGbxInformationAndSetUid();

                if (Map::whereUid($mxMap->uid)->exists()) {
                    //Map with uid found
                    $map = Map::whereUid($mxMap->uid)->first();

                    if (File::exists(MapController::getMapsPath($map->filename))) {
                        //Map file still exists
                        $mxMap->delete();
                    } else {
                        //Map file was moved or removed
                        $mxMap->moveTo('MX');
                        $map->filename = $mxMap->getFilename();
                    }

                    $map->enabled  = true;
                    $map->cooldown = 999;
                    $map->saveOrFail();
                } else {
                    //Map uid not found
                    $mxMap->loadMxDetails();
                    $mxMap->moveTo('MX');

                    if (Map::whereFilename($mxMap->getFilename())->exists()) {
                        $map = Map::whereFilename($mxMap->getFilename())->first();
                        $map->locals()->delete();
                        $map->ratings()->delete();
                    } else {
                        $map = new Map();
                    }

                    $map->uid        = $mxMap->uid;
                    $map->author     = self::getAuthorId($mxMap->gbx->AuthorLogin);
                    $map->gbx        = $mxMap->gbxString;
                    $map->mx_details = json_encode($mxMap->mxDetails);
                    $map->filename   = $mxMap->getFilename();
                    $map->enabled    = true;
                    $map->cooldown   = 999;
                    $map->saveOrFail();
                }

                if (!Server::isFilenameInSelection($map->filename)) {
                    try {
                        Server::addMap($map->filename);
                    } catch (\Exception $e) {
                        Log::logAddLine('MxDownload', 'Adding map to selection failed: ' . $e->getMessage());

                        if (!Server::isFilenameInSelection($map->filename)) {
                            $map->enabled = false;
                            $map->save();
                        }

                        continue;
                    }
                }

                if ($map->enabled) {
                    //Save the map to the matchsettings
                    if (!MatchSettingsController::filenameExistsInCurrentMatchSettings($map->filename)) {
                        MatchSettingsController::addMapToCurrentMatchSettings($map);
                    }

                    infoMessage($player, ' added map ', $map)->sendAll();

                    Log::logAddLine('MxDownload', $player . '(' . $player->Login . ') added map ' . $map . ' [' . $map->uid . ']');

                    //Send updated map-list
                    Hook::fire('MapPoolUpdated');

                    //Queue the newly added map
                    QueueController::queueMap($player, $map);
                } else {
                    warningMessage("Failed to add map $mxId")->send($player);
                }
            } catch (\Exception $e) {
                Log::logAddLine('MxDownload', $e->getMessage());
            }
        }
    }

    private static function getAuthorId($authorLogin): int
    {
        if (Player::where('Login', $authorLogin)->exists()) {
            return Player::find($authorLogin)->id;
        } else {
            return Player::insertGetId([
                'Login'    => $authorLogin,
                'NickName' => $authorLogin,
            ]);
        }
    }
}
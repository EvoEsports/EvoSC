<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
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

    public static function addMap(Player $player, $cmd, string ...$arguments)
    {
        foreach ($arguments as $mxId) {
            $mxId = (int)$mxId;

            if ($mxId == 0) {
                Log::warning("Requested map with invalid id: " . $mxId);
                warningMessage("Requested map with invalid id: $mxId")->send($player);

                continue;
            }

            $infoResponse = RestClient::get('https://api.mania-exchange.com/tm/maps/' . $mxId);

            if ($infoResponse->getStatusCode() != 200) {
                Log::error("ManiaExchange request failed (" . $infoResponse->getStatusCode() . ") " . $infoResponse->getReasonPhrase());
                warningMessage('Can not reach mania exchange.')->send($player);

                continue;
            }

            $body = $infoResponse->getBody()->getContents();
            $info = json_decode($body);

            if (!$info) {
                Log::error('Failed to get info for mx id: ' . $mxId);
                warningMessage('Failed to get info from ManiaExchange for mx id ', secondary($mxId))->send($player);

                continue;
            }

            $info = $info[0];

            if ($info && Map::whereUid($info->TrackUID)->exists()) {
                //Map already exists
                $map = Map::whereUid($info->TrackUID)->first();

                if (!$map->enabled) {
                    $map->update([
                        'enabled'  => 1,
                        'cooldown' => 999,
                    ]);
                    infoMessage($player, ' enabled ', $map)->sendAll();
                    Log::logAddLine('MxDownload', $player . ' enabled map ' . $map);
                } else {
                    warningMessage('Map ', $map, ' already exists and is enabled.')->send($player);
                }

                $filename = $map->filename;
            } else {
                //Map does not exist
                $download = RestClient::get('http://tm.mania-exchange.com/tracks/download/' . $mxId);

                if ($download->getStatusCode() != 200) {
                    Log::error("ManiaExchange request failed (" . $infoResponse->getStatusCode() . ") " . $infoResponse->getReasonPhrase());
                    warningMessage('Can not reach mania exchange.')->send($player);

                    continue;
                }

                if ($download->getHeader('Content-Type')[0] != 'application/x-gbx') {
                    Log::warning('Not a valid GBX.');

                    continue;
                }

                $filename = preg_replace('/^attachment; filename="(.+)"$/', '\1', $download->getHeader('content-disposition')[0]);
                $filename = html_entity_decode(trim($filename), ENT_QUOTES | ENT_HTML5);
                $filename = str_replace('..', '.', $filename);
                $filename = 'MX/' . $filename;

                $mapFolder = MapController::getMapsPath();

                if (!File::dirExists($mapFolder . 'MX')) {
                    File::makeDir($mapFolder . 'MX');
                }

                $body     = $download->getBody();
                $absolute = $mapFolder . $filename;
                $tempFile = $mapFolder . 'MX/_download.Gbx';

                Log::logAddLine('MxDownload', 'Deleting existing download file.');
                File::delete($tempFile);
                File::put($tempFile, $body);

                if (!File::exists($tempFile)) {
                    warningMessage('Adding map ' . secondary($info->Name) . ' failed.')->send($player);
                    continue;
                }

                Log::logAddLine('MxDownload', 'Creating temp-file finished.');

                $gbxInfo = MapController::getGbxInformation($tempFile);
                $gbx     = json_decode($gbxInfo);
                $uid     = $gbx->MapUid;

                if (Map::whereFilename($filename)->exists()) {
                    //Map was updated
                    $map = Map::whereFilename($filename)->first();

                    LocalRecord::whereMap($map->id)->delete();
                    Dedi::whereMap($map->id)->delete();
                    MapFavorite::whereMapId($map->id)->delete();
                    MapQueue::whereMapUid($map->uid)->delete();

                    $map->update([
                        'gbx'             => $gbxInfo,
                        'uid'             => $uid,
                        'cooldown'        => 999,
                        'enabled'         => 1,
                        'mx_details'      => null,
                        'mx_world_record' => null,
                    ]);

                    Log::logAddLine('MxDownload', 'Delete old map version of ' . $map->filename);
                    File::delete($absolute);

                    infoMessage($player, ' updated map ', $map, ' to the latest version.')->sendAll();
                    Log::logAddLine('MxDownload', $player . ' updated map ' . $map);
                } else {
                    if (Player::where('Login', $gbx->AuthorLogin)->exists()) {
                        $authorId = Player::find($gbx->AuthorLogin)->id;
                    } else {
                        $authorId = Player::insertGetId([
                            'Login'    => $gbx->AuthorLogin,
                            'NickName' => $gbx->AuthorLogin,
                        ]);
                    }

                    $map           = new Map();
                    $map->gbx      = $gbxInfo;
                    $map->uid      = $uid;
                    $map->filename = $filename;
                    $map->author   = $authorId;
                    $map->cooldown = 999;
                    $map->enabled  = 1;
                    $map->saveOrFail();

                    infoMessage($player, ' added new map ', $map)->sendAll();

                    Log::logAddLine('MxDownload', $player . ' added new map ' . $gbx->Name);
                }

                rename($tempFile, $absolute);
            }

            //Send updated map-list
            Hook::fire('MapPoolUpdated');

            //Add the map to the selection
            if (!collect(Server::getMapList())->contains('fileName', $filename)) {
                try {
                    Server::addMap($filename);
                } catch (\Exception $e) {
                    Log::logAddLine('MxDownload', 'Adding map to selection failed: ' . $e->getMessage());

                    continue;
                }
            }

            //Queue the newly added map
            QueueController::queueMap($player, $map);

            //Save the map to the matchsettings
            if (MatchSettingsController::filenameExistsInCurrentMatchSettings($filename)) {
                MatchSettingsController::removeByFilenameFromCurrentMatchSettings($filename);
            }

            MatchSettingsController::addMapToCurrentMatchSettings($map);
        }
    }
}
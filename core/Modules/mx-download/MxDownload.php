<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Classes\ChatCommand;
use esc\Controllers\MapController;
use esc\Controllers\QueueController;
use esc\Models\Map;
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

                return;
            }

            $response = RestClient::get('http://tm.mania-exchange.com/tracks/download/' . $mxId);

            if ($response->getStatusCode() != 200) {
                Log::error("ManiaExchange returned with non-success code [" . $response->getStatusCode() . "] " . $response->getReasonPhrase());
                warningMessage('Can not reach mania exchange.')->send($player);

                return;
            }

            if ($response->getHeader('Content-Type')[0] != 'application/x-gbx') {
                Log::warning('Not a valid GBX.');

                return;
            }

            $filename = preg_replace('/^attachment; filename="(.+)"$/', '\1', $response->getHeader('content-disposition')[0]);
            $filename = html_entity_decode(trim($filename), ENT_QUOTES | ENT_HTML5);
            $filename = str_replace('..', '.', $filename);
            $filename = 'MX/' . $filename;

            $mapFolder = MapController::getMapsPath();

            if (!File::dirExists($mapFolder . 'MX')) {
                File::makeDir($mapFolder . 'MX');
            }

            $body     = $response->getBody();
            $absolute = $mapFolder . $filename;
            $tempFile = $mapFolder . '_download.Gbx';

            File::delete($tempFile);
            File::put($tempFile, $body);

            if (!File::exists($tempFile)) {
                warningMessage("Map download ($mxId) failed.")->send($player);
                continue;
            }

            $checksum = md5_file($tempFile);
            $gbxInfo  = MapController::getGbxInformation($tempFile);
            $gbx      = json_decode($gbxInfo);
            $mapUid   = $gbx->MapUid;
            $map      = Map::whereUid($mapUid)->first();

            if ($map) {
                $compareToChecksum = $map->checksum ?: md5_file($mapFolder . $map->filename);

                if ($compareToChecksum == $checksum) {
                    File::delete($tempFile);

                    if ($map->enabled) {
                        warningMessage('The map ', $map, ' is already in the selection up to date with MX.')->send($player);

                        return;
                    } else {
                        $map->update([
                            'enabled'  => true,
                            'cooldown' => config('server.map-cooldown'),
                        ]);

                        $message = infoMessage($player, ' enabled map ', $map);
                    }
                } else {
                    // $existingMap->locals()->delete();

                    $map->update([
                        'filename' => $filename,
                        'cooldown' => config('server.map-cooldown'),
                        'checksum' => $checksum,
                        'enabled'  => true,
                    ]);

                    File::delete($absolute);
                    rename($tempFile, $absolute);

                    $message = infoMessage($player, ' updated map ', $map, ' to the latest version.');
                }
            } else {
                if (Player::whereLogin($gbx->AuthorLogin)->exists()) {
                    $authorId = Player::whereLogin($gbx->AuthorLogin)->first()->id;
                } else {
                    $authorId = Player::insertGetId([
                        'Login'    => $gbx->AuthorLogin,
                        'NickName' => $gbx->AuthorLogin,
                    ]);
                }

                File::delete($absolute);
                rename($tempFile, $absolute);

                $map = Map::create([
                    'uid'      => $mapUid,
                    'author'   => $authorId,
                    'filename' => $filename,
                    'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'enabled'  => true,
                    'cooldown' => config('server.map-cooldown'),
                    'checksum' => $checksum,
                ]);

                $message = infoMessage($player, ' added new map ', $map);
            }

            Hook::fire('MapPoolUpdated');

            try {
                Server::addMap($filename);
            } catch (\Exception $e) {
                Log::logAddLine('MxDownload', 'Adding map to selection failed: ' . $e->getMessage());

                return;
            }

            try {
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings')); //TODO: Save to current matchsettings
            } catch (\Exception $e) {
                Log::logAddLine('MxDownload', 'Saving match-settings failed: ' . $e->getMessage());
            }

            if (isset($map)) {
                QueueController::queueMap($player, $map);
            }

            if (isset($message)) {
                $message->sendAll();
            }
        }
    }
}
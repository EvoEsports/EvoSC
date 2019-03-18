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

            $map = Map::getByMxId($mxId);

            if ($map && File::exists(MapController::getMapsPath(), 'MX/' . $map->filename)) {
                infoMessage($player, ' added map: ', $map)->sendAll();
                $map->update(['enabled' => true]);
                Server::addMap($map->filename);
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));
                QueueController::queueMap($player, $map);
                Hook::fire('MapPoolUpdated');
                continue;
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

            $filename = preg_replace('/^attachment; filename="(.+)"$/', '\1',
                $response->getHeader('content-disposition')[0]);
            $filename = html_entity_decode(trim($filename), ENT_QUOTES | ENT_HTML5);
            $filename = str_replace('..', '.', $filename);
            $filename = 'MX/' . $filename;

            $mapFolder = MapController::getMapsPath();

            if (!is_dir($mapFolder . 'MX')) {
                mkdir($mapFolder . 'MX');
            }

            $body     = $response->getBody();
            $absolute = "$mapFolder$filename";

            File::put($absolute, $body);

            if (!File::exists($absolute)) {
                warningMessage("Map download ($mxId) failed.")->send($player);
                continue;
            }


            $gbxInfo = MapController::getGbxInformation($filename);
            $gbx     = json_decode($gbxInfo);

            $author = Player::whereLogin($gbx->AuthorLogin)->first();

            if (!$author) {
                $authorId = Player::insertGetId([
                    'Login'    => $gbx->AuthorLogin,
                    'NickName' => $gbx->AuthorLogin,
                ]);
            } else {
                $authorId = $author->id;
            }

            if ($map) {
                $map->update([
                    'author'   => $authorId,
                    'filename' => $filename,
                    'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'enabled'  => 1,
                ]);
            } else {
                $map = Map::firstOrCreate([
                    'uid'      => $gbx->MapUid,
                    'author'   => $authorId,
                    'filename' => $filename,
                    'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                    'enabled'  => 1,
                ]);
            }

            try {
                Server::addMap($map->filename);
                Server::saveMatchSettings('MatchSettings/' . config('server.default-matchsettings'));
                QueueController::queueMap($player, $map);
                Hook::fire('MapPoolUpdated');
            } catch (\Exception $e) {
                Log::warning("Map $map->filename already added.");
            }


            infoMessage('New map added: ', $map)->sendAll();
        }
    }
}
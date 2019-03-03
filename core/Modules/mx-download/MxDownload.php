<?php

namespace esc\Modules;


use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Map;
use esc\Models\Player;

class MxDownload
{
    public function __construct()
    {
        ChatController::addCommand('add', [self::class, 'addMap'], 'Add a map from mx. Usage: //add \<mxid\>', '//', 'map_add');
    }

    /**
     * Add map from MX
     *
     * @param \esc\Models\Player $player
     * @param                    $cmd
     * @param string             ...$arguments
     */
    public static function addMap(Player $player, $cmd, string ...$arguments)
    {
        foreach ($arguments as $mxId) {
            $mxId = (int)$mxId;

            if ($mxId == 0) {
                Log::warning("Requested map with invalid id: " . $mxId);
                ChatController::message($player, "Requested map with invalid id: " . $mxId);

                return;
            }

            $map = Map::getByMxId($mxId);

            if ($map && File::exists(MapController::getMapsPath() . $map->filename)) {
                ChatController::message($player, '_warning', secondary($map), ' already exists');
                continue;
            }

            $response = RestClient::get('http://tm.mania-exchange.com/tracks/download/' . $mxId);

            if ($response->getStatusCode() != 200) {
                Log::error("ManiaExchange returned with non-success code [" . $response->getStatusCode() . "] " . $response->getReasonPhrase());
                ChatController::message($player, "Can not reach mania exchange.");

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

            $mapFolder = MapController::getMapsPath() . 'MX/';

            if (!is_dir($mapFolder)) {
                mkdir($mapFolder);
            }

            $body     = $response->getBody();
            $absolute = "$mapFolder$filename";

            File::put($absolute, $body);

            if (!File::exists($absolute)) {
                ChatController::message($player, '_warning', "Map download ($mxId) failed.");
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
                Hook::fire('MapPoolUpdated');
            } catch (\Exception $e) {
                Log::warning("Map $map->filename already added.");
            }


            ChatController::message(onlinePlayers(), '_info', 'New map added: ', $map);
        }
    }
}
<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Controllers\MapController;
use esc\Controllers\QueueController;
use esc\Models\Map;
use esc\Models\Player;

class AddMapTemp
{
    public function __construct()
    {
        ChatCommand::add('/add', [self::class, 'addMap'], 'Vote to play map from mx temporarily. Usage: /add <mx_id>');
    }

    public static function addMap(Player $player, $cmd, $mxId)
    {
        $mxId = (int)$mxId;

        if ($mxId == 0) {
            Log::warning("Requested map with invalid id: " . $mxId);
            warningMessage('Requested map with invalid id: ', $mxId)->send($player);

            return;
        }

        $map = Map::getByMxId($mxId);

        if (!$map) {
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

            if (!is_dir($mapFolder)) {
                mkdir($mapFolder);
            }

            $body     = $response->getBody();
            $absolute = "$mapFolder$filename";

            File::put($absolute, $body);

            if (!File::exists($absolute)) {
                warningMessage("Map download ($mxId) failed.")->send($player);

                return;
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

            Map::create([
                'uid'      => $gbx->MapUid,
                'author'   => $authorId,
                'filename' => $filename,
                'gbx'      => preg_replace("(\n|[ ]{2,})", '', $gbxInfo),
                'enabled'  => 1,
            ]);

            $map = Map::whereUid($gbx->MapUid)->first();
            MxMapDetails::loadMxDetails($map, true);
        }

        Server::insertMap($map->filename);

        try {
            Votes::startVote($player, 'Play ' . secondary($map) . '?', function ($success) use ($map, $player) {
                if ($success) {
                    QueueController::queueMap($player, $map);
                    infoMessage('Vote to add ', secondary($map), ' was successful.')->sendAll();
                    infoMessage($map, ' was added to the queue.')->sendAll();
                    Hook::fire('MapPoolUpdated');

                    Hook::add('BeginMatch', function () use ($map) {
                        $map->update(['enabled' => 0]);
                        Hook::fire('MapPoolUpdated');
                    }, true);
                } else {
                    infoMessage('Vote to add ', secondary($map), ' failed.')->sendAll();
                }
            });
        } catch (\Exception $e) {
            Log::warning($e->getMessage());
        }
    }
}
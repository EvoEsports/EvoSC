<?php

namespace esc\Modules;


use esc\Classes\Cache;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\MxMap;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Classes\ChatCommand;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Controllers\MatchSettingsController;
use esc\Controllers\QueueController;
use esc\Models\Map;
use esc\Models\Player;
use GuzzleHttp\Exception\GuzzleException;

class MxDownload
{
    public function __construct()
    {
        if (!File::dirExists(cacheDir('mx'))) {
            File::makeDir(cacheDir('mx'));
        }

        ChatCommand::add('//add', [self::class, 'showAddMapInfo'], 'Add a map from mx. Usage: //add <mx_id>',
            'map_add');

        ManiaLinkEvent::add('mx.add', [self::class, 'addMap'], 'map_add');
    }

    /**
     * @param  \esc\Models\Player  $player
     * @param                    $cmd
     * @param  string  ...$arguments
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Throwable
     */
    public static function showAddMapInfo(Player $player, $cmd, string ...$arguments)
    {
        foreach ($arguments as $mxId) {
            try {
                $details = self::loadMxDetails($mxId);

                Template::show($player, 'mx-download.add-map-info', compact('details'));
            } catch (\Exception $e) {
                Log::write($e->getMessage());
            } catch (GuzzleException $e) {
                Log::write($e->getMessage());
            }
        }
    }

    public static function addMap(Player $player, $mxId)
    {
        try {
            $mxMap = MxMap::get($mxId);
            $mxMap->mxDetails = self::loadMxDetails($mxId);
            $mxMap->loadGbxInformationAndSetUid();
        } catch (\Exception $e) {
            Log::write($e->getMessage());
        }

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

            $map->enabled = true;
            $map->cooldown = 999;
            $map->saveOrFail();
        } else {
            //Map uid not found
            $mxMap->moveTo('MX');

            if (Map::whereFilename($mxMap->getFilename())->exists()) {
                $map = Map::whereFilename($mxMap->getFilename())->first();
                $map->locals()->delete();
                $map->ratings()->delete();
            } else {
                $map = new Map();
            }

            $map->uid = $mxMap->uid;
            $map->author = self::getAuthorId($mxMap->author->Login);
            $map->gbx = $mxMap->gbxString;
            $map->mx_details = json_encode($mxMap->mxDetails);
            $map->filename = $mxMap->getFilename();
            $map->enabled = true;
            $map->cooldown = 999;
            $map->saveOrFail();
        }

        if (!Server::isFilenameInSelection($map->filename)) {
            try {
                Server::addMap($map->filename);
            } catch (\Exception $e) {
                Log::write('Adding map to selection failed: '.$e->getMessage());

                if (!Server::isFilenameInSelection($map->filename)) {
                    $map->enabled = false;
                    $map->save();
                }

                return;
            }
        }

        if ($map->enabled) {
            //Save the map to the matchsettings
            if (!MatchSettingsController::filenameExistsInCurrentMatchSettings($map->filename)) {
                MatchSettingsController::addMapToCurrentMatchSettings($map);
            }

            infoMessage($player, ' added map ', $map)->sendAll();

            Log::write($player.'('.$player->Login.') added map '.$map.' ['.$map->uid.']');

            //Send updated map-list
            Hook::fire('MapPoolUpdated');

            //Queue the newly added map
            QueueController::queueMap($player, $map);
        } else {
            warningMessage("Failed to add map $mxId")->send($player);
        }
    }

    private static function getAuthorId($authorLogin): int
    {
        if (Player::where('Login', $authorLogin)->exists()) {
            return Player::find($authorLogin)->id;
        } else {
            return Player::insertGetId([
                'Login' => $authorLogin,
                'NickName' => $authorLogin,
            ]);
        }
    }

    public static function parseBB(string $bbEncoded): string
    {
        //bbcode
        $bbEncoded = preg_replace('/\[b\](.+?)\[\/b\]/', '$o$1$z', $bbEncoded);
        $bbEncoded = preg_replace('/\[url=(.+?)\](.+?)\[\/url\]/', '$l[$1]$2', $bbEncoded);
        $bbEncoded = preg_replace('/\[youtube\](.+?)\[\/youtube\]/', '$l[$1]Video', $bbEncoded);

        //smileys
        $bbEncoded = str_replace(':cool:', '', $bbEncoded);
        $bbEncoded = str_replace(':stunned:', '', $bbEncoded);
        $bbEncoded = str_replace(':sad:', '', $bbEncoded);
        $bbEncoded = str_replace(':love:', '', $bbEncoded);
        $bbEncoded = str_replace(':heart:', '', $bbEncoded);
        $bbEncoded = str_replace(':tongue:', '$o:p$z', $bbEncoded);
        $bbEncoded = str_replace(':thumbsup:', '', $bbEncoded);
        $bbEncoded = str_replace(':thumbsdown:', '', $bbEncoded);
        $bbEncoded = str_replace(':done:', '', $bbEncoded);
        $bbEncoded = str_replace(':undone:', '', $bbEncoded);
        $bbEncoded = str_replace(':build:', '🔨', $bbEncoded);
        $bbEncoded = str_replace(':wait:', '', $bbEncoded);
        $bbEncoded = str_replace(':bronze:', '$c73🏆$z', $bbEncoded);
        $bbEncoded = str_replace(':silver:', '$999🏆$z', $bbEncoded);
        $bbEncoded = str_replace(':gold:', '$fd0🏆$z', $bbEncoded);
        $bbEncoded = str_replace(':award:', '$fe0🏆$z', $bbEncoded);

        return $bbEncoded;
    }

    /**
     * @param $mxId
     * @return
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public static function loadMxDetails($mxId)
    {
        if (Cache::has("mx/{$mxId}_details")) {
            return Cache::get("mx/{$mxId}_details");
        }

        $infoResponse = RestClient::get('https://api.mania-exchange.com/tm/maps/'.$mxId);

        if ($infoResponse->getStatusCode() != 200) {
            throw new \Exception('Failed to get mx-details: '.$infoResponse->getReasonPhrase());
        }

        $detailsBody = $infoResponse->getBody()->getContents();
        $info = json_decode($detailsBody);

        if (!$info || isset($info->StatusCode)) {
            throw new \Exception('Failed to parse mx-details: '.$detailsBody);
        }

        Cache::put("mx/{$mxId}_details", $info[0], now()->addMinutes(2));

        return $info[0];
    }
}
<?php

namespace esc\Modules;


use esc\Classes\Cache;
use esc\Classes\ChatCommand;
use esc\Classes\DB;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Controllers\MatchSettingsController;
use esc\Controllers\QueueController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
use esc\Models\Player;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

class MxDownload extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ChatCommand::add('//add', [self::class, 'showAddMapInfo'], 'Add a map from mx. Usage: //add <mx_id>', 'map_add');

        ManiaLinkEvent::add('mx.add', [self::class, 'addMap'], 'map_add');
    }

    /**
     * @param Player $player
     * @param string ...$arguments
     */
    public static function showAddMapInfo(Player $player, string ...$arguments)
    {
        foreach ($arguments as $mxId) {
            if (intval($mxId) == 0) {
                continue;
            }

            try {
                $details = self::loadMxDetails($mxId);

                Template::show($player, 'mx-download.add-map-info', compact('details'));
            } catch (Exception $e) {
                Log::write($e->getMessage());
            } catch (GuzzleException $e) {
                Log::write($e->getMessage());
            }
        }
    }

    /**
     * @param int $mxId
     * @return string|string[]|null
     * @throws Exception
     */
    private static function downloadMapAndGetFilename(int $mxId)
    {
        if (!$mxId || $mxId == 0) {
            throw new Exception("Requested map with invalid id: $mxId");
        }

        $download = RestClient::get('http://tm.mania-exchange.com/tracks/download/' . $mxId);

        if ($download->getStatusCode() != 200) {
            throw new Exception("Download $mxId failed: " . $download->getReasonPhrase());
        }

        Log::write("Request $mxId finished.", true);

        if ($download->getHeader('Content-Type')[0] != 'application/x-gbx') {
            throw new Exception('File is not a valid GBX.');
        }

        $filename = preg_replace('/^attachment; filename="(.+)"$/', '\1', $download->getHeader('content-disposition')[0]);
        $filename = html_entity_decode(trim($filename), ENT_QUOTES | ENT_HTML5);
        $filename = preg_replace('/[^a-z0-9\-_# .]/i', '', $filename);
        $filename = preg_replace('/\s/i', '_', $filename);
        $filename = "MX/$mxId" . "_$filename";

        Log::write('Saving map as ' . MapController::getMapsPath($filename), true);
        File::put(MapController::getMapsPath($filename), $download->getBody()->getContents());

        if (!File::exists(MapController::getMapsPath($filename))) {
            throw new Exception('Map download failed, map does not exist.', true);
        }

        return $filename;
    }

    /**
     * Download map from mx and add it to the map-cool
     *
     * @param Player $player
     * @param $mxId
     * @throws GuzzleException
     * @throws Throwable
     */
    public static function addMap(Player $player, int $mxId)
    {
        if ($mxId <= 0) {
            warningMessage('MX-ID invalid.')->send($player);
            return;
        }

        $filename = self::downloadMapAndGetFilename(intval($mxId));
        $gbx = json_decode(MapController::getGbxInformation($filename, true));

        dump($gbx);

        if (!isset($gbx->MapUid)) {
            warningMessage('Could not load UID for map.')->send($player);
            return;
        }

        if (DB::table(Map::TABLE)->where('filename', '=', $filename)->where('uid', '!=', $gbx->MapUid)->exists()) {
            $oldMap = DB::table(Map::TABLE)->where('filename', '=', $filename)->where('uid', '!=', $gbx->MapUid)->first();

            DB::table('dedi-records')->where('Map', '=', $oldMap->id)->delete();
            DB::table('local-records')->where('Map', '=', $oldMap->id)->delete();
            $oldMap->delete();
        }

        DB::table(Map::TABLE)->updateOrInsert([
            'uid' => $gbx->MapUid
        ], [
            'author' => MapController::createOrGetAuthor($gbx->AuthorLogin),
            'filename' => $filename,
            'name' => $gbx->Name,
            'environment' => $gbx->Environment,
            'title_id' => $gbx->TitleId,
            'enabled' => 1,
            'cooldown' => config('server.map-cooldown', 10),
            'mx_id' => $mxId
        ]);

        if (!Server::isFilenameInSelection($filename)) {
            try {
                Server::addMap($filename);
                Log::info("Added $filename to the selection.");
            } catch (Exception $e) {
                warningMessage('Failed to add map ', secondary($gbx->Name), ' to the map-pool')->send($player);
                Log::write('Adding map to selection failed: ' . $e->getMessage());
                return;
            }
        }

        //Save the map to the matchsettings
        if (!MatchSettingsController::filenameExistsInCurrentMatchSettings($filename)) {
            MatchSettingsController::addMapToCurrentMatchSettings($filename, $gbx->MapUid);
        }

        infoMessage($player, ' added map ', secondary($gbx->Name))->sendAll();
        Log::write($player . '(' . $player->Login . ') added map ' . $filename . ' [' . $gbx->MapUid . ']');

        //Send updated map-list
        Hook::fire('MapPoolUpdated');

        //Queue the newly added map
        if (QueueController::queueMapByUid($player, $gbx->MapUid)) {
            infoMessage(secondary($player), ' queued map ', secondary($gbx->Name))->sendAll();
        }
    }

    /**
     * Parses BBCode to ManiaPlanet format
     *
     * @param string $bbEncoded
     * @return string
     */
    public static function parseBB(string $bbEncoded): string
    {
        $bbEncoded = ml_escape($bbEncoded);

        //bbcode
        $bbEncoded = preg_replace('/\[b](.+?)\[\/b]/', '$o$1$z', $bbEncoded);
        $bbEncoded = preg_replace('/\[b](.+?)\n/', '$o$1$z', $bbEncoded);
        $bbEncoded = preg_replace('/\[i](.+?)\[\/i]/', '$i$1$z', $bbEncoded);
        $bbEncoded = preg_replace('/\[i](.+?)\n/', '$i$1$z', $bbEncoded);
        $bbEncoded = preg_replace('/\[u](.+?)\[\/u]/', '$1', $bbEncoded);
        $bbEncoded = preg_replace('/\[u](.+?)\n/', '$1', $bbEncoded);
        $bbEncoded = preg_replace('/\[url=(.+?)](.+?)\[\/url]/', '$l[$1]$2', $bbEncoded);
        $bbEncoded = preg_replace('/\[youtube](.+?)\[\/youtube]/', '$l[$1]Video', $bbEncoded);

        //smileys
        $bbEncoded = str_replace(':)', '', $bbEncoded);
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
        $bbEncoded = str_replace(':bronze:', '$c73🏆$fff', $bbEncoded);
        $bbEncoded = str_replace(':silver:', '$999🏆$fff', $bbEncoded);
        $bbEncoded = str_replace(':gold:', '$fd0🏆$fff', $bbEncoded);
        $bbEncoded = str_replace(':award:', '$fe0🏆$fff', $bbEncoded);

        return $bbEncoded;
    }

    /**
     * Get mx-details by id
     *
     * @param $mxId
     * @return string
     * @throws Exception
     */
    public static function loadMxDetails($mxId)
    {
        if (Cache::has("mx-details/{$mxId}")) {
            return Cache::get("mx-details/{$mxId}");
        }

        $infoResponse = RestClient::get('https://api.mania-exchange.com/tm/maps/' . $mxId);

        if ($infoResponse->getStatusCode() != 200) {
            throw new Exception('Failed to get mx-details: ' . $infoResponse->getReasonPhrase());
        }

        $detailsBody = $infoResponse->getBody()->getContents();
        $info = json_decode($detailsBody);

        if (!$info || isset($info->StatusCode)) {
            throw new Exception('Failed to parse mx-details: ' . $detailsBody);
        }

        Cache::put("mx-details/{$mxId}", $info[0], now()->addMinutes(30));

        return $info[0];
    }
}
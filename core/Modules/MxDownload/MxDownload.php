<?php

namespace EvoSC\Modules\MxDownload;


use EvoSC\Classes\Cache;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Exchange;
use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\MatchSettingsController;
use EvoSC\Controllers\QueueController;
use EvoSC\Exceptions\InvalidArgumentException;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use Exception;
use Throwable;

class MxDownload extends Module implements ModuleInterface
{
    const MAP_SIZE_LIMIT_ONLINE = 6291456;
    const MAP_SIZE_LIMIT_ONLINE_HUMAN_READABLE = '6mb';

    private static string $apiUrl;
    private static string $exchangeUrl;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isManiaPlanet()) {
            self::$apiUrl = Exchange::MANIAPLANET_MX_API_URL;
            self::$exchangeUrl = Exchange::MANIAPLANET_MX_URL;
        } else {
            self::$apiUrl = Exchange::TRACKMANIA_MX_API_URL;
            self::$exchangeUrl = Exchange::TRACKMANIA_MX_URL;
        }

        ChatCommand::add('//add', [self::class, 'showAddMapInfo'], 'Add a map from mx. Usage: //add <mx_id>', 'map_add');

        ManiaLinkEvent::add('mx.add', [self::class, 'addMap'], 'map_add');
    }

    /**
     * @param Player $player
     * @param string $cmd
     * @param string $tmxId
     * @throws Throwable
     */
    public static function showAddMapInfo(Player $player, string $cmd, string $tmxId)
    {
        if (($tmxId = intval($tmxId)) <= 0) {
            return;
        }

        try {
            $details = self::loadMxDetails($tmxId);

            if (isset($details->Downloadable) && $details->Downloadable == false) {
                warningMessage('Downloading this map has been disabled, sorry.')->send($player);
                return;
            }
        } catch (Exception $e) {
            Log::errorWithCause("The map $tmxId is unknown", $e);
            dangerMessage('The map with the id ', secondary($tmxId), ' is unknown.')->send($player);
            return;
        }

        try {
            $comment = self::parseBB($details->Comments);
            Template::show($player, 'MxDownload.add-map-info', compact('details', 'comment'));
        } catch (Exception $e) {
            Log::errorWithCause('Failed to parse map info', $e);
            warningMessage('Failed to parse map info, adding...')->send($player);
            self::addMap($player, $tmxId);
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

        $download = RestClient::get(self::$exchangeUrl . '/tracks/download/' . $mxId);

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
        $filename = "MX" . DIRECTORY_SEPARATOR . "$mxId" . "_$filename";

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
     * @throws Throwable
     */
    public static function addMap(Player $player, int $mxId)
    {
        if ($mxId <= 0) {
            warningMessage('Exchange-ID invalid.')->send($player);
            return;
        }

        $filename = self::downloadMapAndGetFilename(intval($mxId));

        if (!isManiaPlanet() && Server::getSystemInfo()->isDedicated) {
            $absoluteFilename = mapsDir($filename);
            if (filesize($absoluteFilename) > self::MAP_SIZE_LIMIT_ONLINE) {
                warningMessage('The map filesize is over ', secondary(self::MAP_SIZE_LIMIT_ONLINE_HUMAN_READABLE), ', the map can not be played online.')->send($player);
                unlink($absoluteFilename);
                return;
            }
        }

        $mxDetails = self::loadMxDetails($mxId);
        Log::write(json_encode($mxDetails));
        $gbx = MapController::getGbxInformation($filename);
        Log::write(json_encode($gbx));

        if (!isset($gbx->MapUid)) {
            warningMessage('Could not load UID for map.')->send($player);
            return;
        }

        if (DB::table(Map::TABLE)->where('filename', '=', $filename)->where('uid', '!=', $gbx->MapUid)->exists()) {
            $oldMap = DB::table(Map::TABLE)->where('filename', '=', $filename)->where('uid', '!=', $gbx->MapUid)->first();

            DB::table('dedi-records')->where('Map', '=', $oldMap->id)->delete();
            DB::table('local-records')->where('Map', '=', $oldMap->id)->delete();
            DB::table(Map::TABLE)->where('id', '=', $oldMap->id)->delete();
        }

        DB::table(Map::TABLE)->updateOrInsert([
            'uid' => $gbx->MapUid
        ], [
            'author' => MapController::createOrGetAuthor($gbx->AuthorLogin, $mxDetails->Username),
            'filename' => $filename,
            'name' => $gbx->Name,
            'environment' => $gbx->Environment,
            'title_id' => $gbx->TitleId,
            'folder' => substr($filename, 0, strrpos($filename, DIRECTORY_SEPARATOR)),
            'enabled' => 1,
            'cooldown' => config('server.map-cooldown', 10),
            'mx_id' => $mxId,
            'exchange_version' => $mxDetails->UpdatedAt
        ]);

        if (!Server::isFilenameInSelection($filename)) {
            try {
                Server::addMap($filename);
                Log::info("Added $filename to the selection.");
            } catch (Exception $e) {
                Log::errorWithCause('Adding map to selection failed', $e);
                warningMessage('Failed to add map ', secondary($gbx->Name), ' to the map-pool: ' . $e->getMessage())->send($player);
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
     * @param int|string $tmxIdOrMapUid
     * @return \stdClass
     * @throws Exception
     */
    public static function loadMxDetails($tmxIdOrMapUid): \stdClass
    {
        if ($tmxIdOrMapUid instanceof Map) {
            throw new InvalidArgumentException('Pass the tmx-id or map-uid.');
        }

        if (Cache::has("mx-details/{$tmxIdOrMapUid}")) {
            return Cache::get("mx-details/{$tmxIdOrMapUid}");
        }

        if (isManiaPlanet()) {
            $infoResponse = RestClient::get(self::$exchangeUrl . '/api/maps/get_map_info/multi/' . $tmxIdOrMapUid, ['timeout' => 3]);
        } else {
            $infoResponse = RestClient::get(self::$apiUrl . '/api/maps/get_map_info/multi/' . $tmxIdOrMapUid, ['timeout' => 3]);
        }

        if ($infoResponse->getStatusCode() != 200) {
            throw new Exception('Failed to get mx-details: ' . $infoResponse->getReasonPhrase());
        }

        $detailsBody = $infoResponse->getBody()->getContents();
        $info = json_decode($detailsBody);

        if (!$info || isset($info->StatusCode)) {
            throw new Exception("Unknown map '$tmxIdOrMapUid'.");
        }

        $info = $info[0];

        Cache::put("mx-details/{$tmxIdOrMapUid}", $info, now()->addMinutes(30));

        return $info;
    }
}

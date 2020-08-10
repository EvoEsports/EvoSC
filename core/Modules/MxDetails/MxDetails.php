<?php

namespace EvoSC\Modules\MxDetails;


use EvoSC\Classes\Cache;
use EvoSC\Classes\Exchange;
use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use GuzzleHttp\Exception\ConnectException;
use stdClass;

class MxDetails extends Module implements ModuleInterface
{
    private static ?string $mxApiUrl = null;  // prevents the "typed static property must not be accessed before initialization" error on Windows
    private static ?string $mxUrl = null;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isManiaPlanet()) {
            self::$mxApiUrl = Exchange::MANIAPLANET_MX_API_URL;
            self::$mxUrl = Exchange::MANIAPLANET_MX_URL;
        } else {
            self::$mxApiUrl = Exchange::TRACKMANIA_MX_API_URL;
            self::$mxUrl = Exchange::TRACKMANIA_MX_URL;
        }

        if (!File::dirExists(cacheDir('mx-details'))) {
            File::makeDir(cacheDir('mx-details'));
        }
        if (!File::dirExists(cacheDir('mx-wr'))) {
            File::makeDir(cacheDir('mx-wr'));
        }

        ManiaLinkEvent::add('mx.details', [self::class, 'showDetails']);
    }

    public static function showDetails(Player $player, $mapIdOrUid)
    {
        if (empty($mapIdOrUid)) {
            return;
        }

        $map = null;
        if (intval($mapIdOrUid) > 0) {
            $map = Map::whereId($mapIdOrUid)->first();
        } else {
            $map = Map::whereUid($mapIdOrUid)->first();
        }

        if (is_null($map)) {
            warningMessage('Unknown map.')->send($player);
            return;
        }

        if (!$map->mx_details) {
            self::loadMxDetails($map);
        }

        if (!$map->mx_world_record && isManiaPlanet()) {
            self::loadMxWordlRecord($map);
        }

        $voteAverage = 0;
        if (Cache::has('mx-details/' . $map->mx_id)) {
            $mxDetails = Cache::get('mx-details/' . $map->mx_id);

            if ($mxDetails && $mxDetails->RatingVoteCount > 0) {
                $voteAverage = $mxDetails->RatingVoteAverage;
            }
        }

        $rating = self::getRatingString($voteAverage);
        Template::show($player, 'MxDetails.window', compact('map', 'rating'));
    }

    private static function getRatingString($average): string
    {
        $starString = '';
        $stars = $average / 20;
        $full = floor($stars);
        $left = $stars - $full;

        for ($i = 0; $i < $full; $i++) {
            $starString .= '';
        }

        if ($left >= 0.5) {
            $starString .= '';
            $full++;
        }

        for ($i = $full; $i < 5; $i++) {
            $starString .= '';
        }

        return $starString;
    }

    /**
     * @param Map $map
     * @return stdClass|null
     */
    public static function loadMxDetails(Map $map)
    {
        try {
            if(isManiaPlanet()) {
                $result = RestClient::get(self::$mxApiUrl . '/maps/' . $map->uid, ['timeout' => 1]);
            }else{
                $result = RestClient::get(self::$mxApiUrl . '/api/maps/get_map_info/multi/' . $map->uid, ['timeout' => 1]);
            }
//            $result = RestClient::get(self::$mxApiUrl . '/maps/get_map_info/multi/' . $map->uid, ['timeout' => 1]);
        } catch (ConnectException $e) {
            Log::error($e->getMessage(), true);
            return null;
        }

        if ($result->getStatusCode() != 200) {
            Log::write('Failed to fetch MX details: ' . $result->getReasonPhrase(), isVerbose());

            return null;
        }

        $data = $result->getBody()->getContents();
        Log::write('Received: ' . $data, isVeryVerbose());
        $data = json_decode($data);

        if (count($data) > 0) {
            if (!$map->mx_id) {
                $map->update([
                    'mx_id' => $data[0]->TrackID
                ]);
            }

            Cache::put('mx-details/' . $data[0]->TrackID, $data[0]);

            return $data[0];
        }

        return null;
    }

    /**
     * @param Map $map
     * @return stdClass|null
     */
    public static function loadMxWordlRecord(Map $map)
    {
        if (!$map->mx_id) {
            return null;
        }

        $result = RestClient::get(self::$mxApiUrl . '/tm/tracks/worldrecord/' . $map->mx_details->TrackID, ['timeout' => 0.75]);

        if ($result->getStatusCode() != 200) {
            Log::write('Failed to fetch MX world record: ' . $result->getReasonPhrase());

            return null;
        }

        $data = json_decode($result->getBody()->getContents());
        Cache::put('mx-wr/' . $map->mx_id, $data);

        return $data;
    }
}
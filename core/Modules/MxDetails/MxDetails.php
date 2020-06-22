<?php

namespace EvoSC\Modules\MxDetails;


use EvoSC\Classes\Cache;
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

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (!File::dirExists(cacheDir('mx-details'))) {
            File::makeDir(cacheDir('mx-details'));
        }
        if (!File::dirExists(cacheDir('mx-wr'))) {
            File::makeDir(cacheDir('mx-wr'));
        }

        ManiaLinkEvent::add('mx.details', [self::class, 'showDetails']);
    }

    public static function showDetails(Player $player, string $mapUid)
    {
        $map = Map::getByUid($mapUid);

        if (!$map) {
            return;
        }

        if (!$map->mx_details) {
            self::loadMxDetails($map);
        }

        if (!$map->mx_world_record) {
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
            $result = RestClient::get('https://api.mania-exchange.com/tm/maps/' . $map->uid, ['timeout' => 5]);
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

        $result = RestClient::get('https://api.mania-exchange.com/tm/tracks/worldrecord/' . $map->mx_details->TrackID);

        if ($result->getStatusCode() != 200) {
            Log::write('Failed to fetch MX world record: ' . $result->getReasonPhrase());

            return null;
        }

        $data = json_decode($result->getBody()->getContents());
        Cache::put('mx-wr/' . $map->mx_id, $data);

        return $data;
    }
}
<?php

namespace esc\Modules;


use esc\Classes\Cache;
use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\RestClient;
use esc\Classes\Template;
use esc\Models\Map;
use esc\Models\Player;

class MxMapDetails
{
    public function __construct()
    {
        ManiaLinkEvent::add('mx.details', [self::class, 'showDetails']);

        if (!File::dirExists(cacheDir('mx-details'))) {
            File::makeDir(cacheDir('mx-details'));
        }
        if (!File::dirExists(cacheDir('mx-wr'))) {
            File::makeDir(cacheDir('mx-wr'));
        }
    }

    public static function showDetails(Player $player, string $mapId)
    {
        $map = Map::find($mapId);

        if (!$map) {
            return;
        }

        if (!$map->mx_details) {
            self::loadMxDetails($map);
        }

        if (!$map->mx_world_record) {
            self::loadMxWordlRecord($map);
        }

        $rating = self::getRatingString($map->average_rating);
        Template::show($player, 'mx-details.window', compact('map', 'rating'));
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
     * @param  Map  $map
     * @return \stdClass|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function loadMxDetails(Map $map)
    {
        if (!$map->mx_id) {
            return null;
        }

        $result = RestClient::get('https://api.mania-exchange.com/tm/maps/'.$map->uid);

        if ($result->getStatusCode() != 200) {
            Log::write('Failed to fetch MX details: '.$result->getReasonPhrase(), isVerbose());

            return null;
        }

        $data = $result->getBody()->getContents();
        Log::write('Received: '.$data, isVeryVerbose());
        $data = json_decode($data);

        Cache::put('mx-details/'.$map->mx_id, $data[0]);

        return $data[0];
    }

    /**
     * @param  Map  $map
     * @return \stdClass|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function loadMxWordlRecord(Map $map)
    {
        if (!$map->mx_id) {
            return null;
        }

        $result = RestClient::get('https://api.mania-exchange.com/tm/tracks/worldrecord/'.$map->mx_details->TrackID);

        if ($result->getStatusCode() != 200) {
            Log::write('Failed to fetch MX world record: '.$result->getReasonPhrase());

            return null;
        }

        $data = json_decode($result->getBody()->getContents());
        Cache::put('mx-wr/'.$map->mx_id, $data);

        return $data;
    }
}
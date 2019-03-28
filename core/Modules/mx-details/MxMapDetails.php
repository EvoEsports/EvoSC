<?php

namespace esc\Modules;


use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\RestClient;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Models\Map;
use esc\Models\Player;

class MxMapDetails
{
    public function __construct()
    {
        ManiaLinkEvent::add('mx.details', [self::class, 'showDetails']);
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

        $rating = self::getRatingString($map->mx_details->RatingVoteAverage);
        Template::show($player, 'mx-details.window', compact('map', 'rating'));
    }

    private static function getRatingString($average): string
    {
        $starString = '';
        $stars      = $average / 20;
        $full       = floor($stars);
        $left       = $stars - $full;

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

    public static function loadMxDetails(Map $map, bool $overwrite = false)
    {
        if ($map->mx_details != null && !$overwrite) {
            return;
        }

        $result = RestClient::get('https://api.mania-exchange.com/tm/maps/' . $map->uid);

        if ($result->getStatusCode() != 200) {
            Log::logAddLine('MapController', 'Failed to fetch MX details: ' . $result->getReasonPhrase());

            return;
        }

        $data = $result->getBody()->getContents();

        if ($data == '[]') {
            Log::logAddLine('MapController', 'No MX information available for: ' . $map->gbx->Name);

            return;
        }

        $map->update(['mx_details' => $data]);
        Log::logAddLine('MapController', 'Updated MX details for track: ' . $map->gbx->Name);

        self::loadMxWordlRecord($map);
    }

    public static function loadMxWordlRecord(Map $map)
    {
        $result = RestClient::get('https://api.mania-exchange.com/tm/tracks/worldrecord/' . $map->mx_details->TrackID);

        if ($result->getStatusCode() != 200) {
            Log::logAddLine('MapController', 'Failed to fetch MX world record: ' . $result->getReasonPhrase());

            return;
        }

        $map->update(['mx_world_record' => $result->getBody()->getContents()]);

        Log::logAddLine('MapController', 'Updated MX world record for track: ' . $map->gbx->Name);
    }
}
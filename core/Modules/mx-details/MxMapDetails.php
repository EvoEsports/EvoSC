<?php

namespace esc\Modules;


use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Map;
use esc\Models\Player;

class MxMapDetails
{
    public function __construct()
    {
        ManiaLinkEvent::add('mx.details', [self::class, 'showDetails']);

        // KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        $mapId = Map::all()->random()->id . "";
        self::showDetails($player, $mapId);
    }

    public static function showDetails(Player $player, string $mapId)
    {
        $map = Map::find($mapId);

        if (!$map) {
            return;
        }

        if (!$map->mx_details) {
            MapController::loadMxDetails($map);
        }

        $rating = self::getRatingString($map->mx_details->RatingVoteAverage);
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
}
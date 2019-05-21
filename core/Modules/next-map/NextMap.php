<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Models\Map;

class NextMap
{
    public function __construct()
    {
        // Hook::add('EndMatch', [self::class, 'showNextMap']);
    }

    public static function showNextMap(Map $map)
    {
        Template::showAll('next-map.widget', compact('map'));
    }
}
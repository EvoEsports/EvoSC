<?php

namespace esc\Modules;


use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;

class NextMap implements ModuleInterface
{
    private static $isRounds;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('Maniaplanet.Podium_Start', [self::class, 'showNextMap']);
    }

    public static function showNextMap()
    {
        $map = MapController::getNextMap();
        $author = DB::table('players')->select('NickName')->where('id', '=', $map->author)->first();

        infoMessage('Upcoming map ', secondary($map))->setIcon('')->sendAll();
        Template::showAll('next-map.widget', compact('map', 'author'));
    }
}
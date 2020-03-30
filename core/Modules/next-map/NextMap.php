<?php

namespace esc\Modules;


use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\Module;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;

class NextMap extends Module implements ModuleInterface
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
        Hook::add('BeginMatch', [self::class, 'hideNextMap']);
    }

    public static function showNextMap()
    {
        $map = MapController::getNextMap();
        $author = DB::table('players')->select('NickName')->where('id', '=', $map->author)->first();

        if (Server::isFilenameInSelection($map->filename)) {
            infoMessage('Upcoming map ', secondary($map->name))->setIcon('')->sendAll();
            Template::showAll('next-map.widget', compact('map', 'author'));
        }
    }

    public static function hideNextMap()
    {
        Template::hideAll('next-map.widget');
    }
}
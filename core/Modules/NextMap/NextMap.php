<?php

namespace EvoSC\Modules\NextMap;


use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\MapController;
use EvoSC\Interfaces\ModuleInterface;

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

    /**
     *
     */
    public static function showNextMap()
    {
        $map = MapController::getNextMap();
        $author = DB::table('players')->select('NickName')->where('id', '=', $map->author)->first();

        if (Server::isFilenameInSelection($map->filename)) {
            infoMessage('Upcoming map ', secondary($map->name), ' by ', secondary($author->NickName))->setIcon('')->sendAll();
            Template::showAll('next-map.widget', compact('map', 'author'));
        }
    }

    /**
     *
     */
    public static function hideNextMap()
    {
        Template::hideAll('next-map.widget');
    }
}
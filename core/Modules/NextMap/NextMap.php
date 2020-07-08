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

        if (Server::isFilenameInSelection($map->filename)) {
            $authorNick = DB::table('players')->select('NickName')->where('id', '=', $map->author)->first()->NickName;
            infoMessage('Upcoming map ', secondary(trim(stripAll($map->name))), ' by ', secondary(trim(stripAll($authorNick)) . '$z'))->setIcon('')->sendAll();
            Template::showAll('NextMap.widget', compact('map', 'authorNick'));
        }
    }

    /**
     *
     */
    public static function hideNextMap()
    {
        Template::hideAll('NextMap.widget');
    }
}
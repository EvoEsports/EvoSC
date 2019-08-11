<?php

namespace esc\Modules;


use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;
use Illuminate\Support\Collection;

class RecordsTable implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function start(string $mode)
    {
    }

    public static function show(Player $player, Collection $records, string $window_title = 'Records')
    {
        $pages = floor($records->count() / 100);
        $records = $records->chunk(100);
        $onlineLogins = onlinePlayers()->pluck('Login');

        Template::show($player, 'records-table.table', compact('records', 'pages', 'onlineLogins', 'window_title'));
    }
}
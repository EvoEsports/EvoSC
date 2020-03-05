<?php

namespace esc\Modules;


use esc\Classes\DB;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class RecordsTable extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ManiaLinkEvent::add('records.graph', [self::class, 'showGraph']);
    }

    public static function show(Player $player, Map $map, Collection $records, string $window_title = 'Records')
    {
        $pages = floor($records->count() / 100);
        $records = $records->chunk(100);
        $onlineLogins = onlinePlayers()->pluck('Login');

        Template::show($player, 'records-table.table',
            compact('records', 'pages', 'onlineLogins', 'window_title', 'map'));
    }

    public static function showGraph(Player $player, $mapId, $window_title, $recordId)
    {
        if ($window_title == 'Local Records') {
            $record = DB::table(LocalRecords::TABLE)->where('id', '=', $recordId)->first();
        } else {
            $record = DB::table(Dedimania::TABLE)->where('id', '=', $recordId)->first();
        }

        if (!$record) {
            return;
        }

        $myRecord = DB::table(Dedimania::TABLE)
            ->where('Map', '=', $record->Map)
            ->where('Player', '=', $player->id)
            ->first();

        if (!$myRecord) {
            $myRecord = DB::table(LocalRecords::TABLE)
                ->where('Map', '=', $record->Map)
                ->where('Player', '=', $player->id)->first();
        }

        if (!$myRecord) {
            infoMessage('You do not have an record to compare to.')->send($player);

            return;
        }

        $diffs = collect();
        $recordCps = explode(',', $record->Checkpoints);
        $myCps = explode(',', $myRecord->Checkpoints);

        for ($i = 0; $i < count($recordCps); $i++) {
            $baseCp = $myCps[$i];
            $compareToCp = $recordCps[$i];

            $diffs->push($compareToCp - $baseCp);
        }

        $target = DB::table('players')->where('id', '=', $record->Player)->first();

        Template::show($player, 'records-table.graph', compact('record', 'myRecord', 'window_title', 'diffs', 'recordCps', 'myCps', 'target'));
    }
}
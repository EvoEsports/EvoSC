<?php

namespace EvoSC\Modules\RecordsTable;


use EvoSC\Classes\DB;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\Dedimania\Dedimania;
use EvoSC\Modules\LocalRecords\LocalRecords;
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
        $isRoyal = ModeController::isRoyal();

        Template::show($player, 'RecordsTable.table',
            compact('records', 'pages', 'onlineLogins', 'window_title', 'map', 'isRoyal'));
    }

    public static function showGraph(Player $player, $mapId, $window_title, $targetRecordRank)
    {
        if ($window_title == 'Local Records') {
            $record = DB::table(LocalRecords::TABLE)->where('Map', '=', $mapId)->where('Rank', '=', $targetRecordRank)->first();
        } else {
            $record = DB::table(Dedimania::TABLE)->where('Map', '=', $mapId)->where('Rank', '=', $targetRecordRank)->first();
        }

        if (!$record) {
            Log::info('Target record not found.');
            return;
        }

        $myRecord = DB::table(Dedimania::TABLE)
            ->where('Map', '=', $mapId)
            ->where('Player', '=', $player->id)
            ->first();

        if (!$myRecord) {
            $myRecord = DB::table(LocalRecords::TABLE)
                ->where('Map', '=', $mapId)
                ->where('Player', '=', $player->id)->first();
        }

        if (!$myRecord) {
            infoMessage('You do not have a record to compare to.')->send($player);

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

        Template::show($player, 'RecordsTable.graph', compact('record', 'myRecord', 'window_title', 'diffs', 'recordCps', 'myCps', 'target'));
    }
}

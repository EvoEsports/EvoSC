<?php


namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
use esc\Models\Player;

class CpDiffs implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('BeginMap', [self::class, 'beginMap']);

        ChatCommand::add('/target', [self::class, 'cmdSetTarget'],
            'Use /target local|dedi|wr|me #id to load CPs of record to bottom widget');
    }

    public static function beginMap(Map $map)
    {
        foreach (onlinePlayers() as $player) {
            self::sendInitialCpDiff($player, $map);
        }
    }

    public static function sendInitialCpDiff(Player $player, Map $map)
    {
        $pb = DB::table('pbs')->where('map_id', '=', $map->id)->where('player_id', '=', $player->id)->first();

        if ($pb) {
            $target = new \stdClass();
            $target->score = $pb->score;
            $target->cps = explode(',', $pb->checkpoints);
            $target->name = ml_escape($player->NickName);

            Template::show($player, 'cp-diffs.widget', compact('target'));
        }
    }

    public static function cmdSetTarget(Player $player, string $cmd, string $type, string $id = null)
    {
        $map = MapController::getCurrentMap();

        switch ($type) {
            case 'local':
                $record = DB::table('local-records')->where('Map', '=', $map->id)->where('Rank', '=', $id)->first();
                break;

            case 'wr':
                $id = 1;

            case 'dedi':
                $record = DB::table('dedi-records')->where('Map', '=', $map->id)->where('Rank', '=', $id)->first();
                break;

            case 'me':
                self::sendInitialCpDiff($player, $map);
                return;
        }

        if (isset($record)) {
            $targetPlayer = Player::whereId($record->Player)->first();

            $target = new \stdClass();
            $target->score = $record->Score;
            $target->cps = explode(',', $record->Checkpoints);
            $target->name = ml_escape($targetPlayer->NickName);

            Template::show($player, 'cp-diffs.widget', compact('target'));
        } else {
            warningMessage('Invalid target specified. See ', secondary('/help'),
                ' for more information.')->send($player);
        }
    }
}
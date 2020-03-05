<?php


namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;
use stdClass;

class CpDiffs extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static Collection $targets;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('PlayerConnect', [self::class, 'requestCpDiffs']);

        ManiaLinkEvent::add('request_cp_diffs', [self::class, 'requestCpDiffs']);

        ChatCommand::add('/target', [self::class, 'cmdSetTarget'],
            'Use /target local|dedi|wr|me #id to load CPs of record to bottom widget');

        ChatCommand::add('/pb', [self::class, 'showPb']);
    }

    public static function requestCpDiffs(Player $player)
    {
        self::sendInitialCpDiff($player, MapController::getCurrentMap());
    }

    public static function beginMap()
    {
        self::$targets = collect();
    }

    public static function playerFinish(Player $player, int $score)
    {
        if ($score == 0) {
            return;
        } else {
            if (self::$targets->has($player->id)) {
                if (self::$targets->get($player->id)->score <= $score) {
                    return;
                }
            }
        }

        self::sendInitialCpDiff($player, MapController::getCurrentMap());
    }

    public static function showPb(Player $player)
    {
        $pb = DB::table('pbs')
            ->where('map_id', '=', MapController::getCurrentMap()->id)
            ->where('player_id', '=', $player->id)
            ->first();

        if ($pb) {
            $cps = collect(explode(',', $pb->checkpoints))->map(function ($cp, $key) {
                return '$666' . ($key + 1) . '|$fff' . formatScore($cp, true);
            })->implode(', ');
            infoMessage('Your PB is ', secondary(formatScore($pb->score, true)), ', checkpoints: ', secondary($cps))->send($player);
        } else {
            infoMessage('You don\'t have a PB on this map yet.')->send($player);
        }
    }

    public static function sendInitialCpDiff(Player $player, Map $map)
    {
        $pb = DB::table('pbs')->where('map_id', '=', $map->id)->where('player_id', '=', $player->id)->first();

        if ($pb != null) {
            $target = new stdClass();
            $target->score = $pb->score;
            $target->cps = explode(',', $pb->checkpoints);
            $target->name = ml_escape($player->NickName);

            self::$targets->put($player->id, $target);
            Template::show($player, 'cp-diffs.widget', compact('target'));
        } else {
            $targetRecord = DB::table('dedi-records')->where('Map', '=', $map->id)->where('Player', '=',
                $player->id)->first();

            if (!$targetRecord) {
                $targetRecord = DB::table('local-records')->where('Map', '=', $map->id)->where('Player', '=',
                    $player->id)->first();
            }

            if (!$targetRecord) {
                infoMessage("You don't have a PB on this map yet.")->send($player);
                return;
            }

            if ($targetRecord->Player == $player->id) {
                $targetPlayer = $player;
            } else {
                $targetPlayer = DB::table('players')->where('id', '=', $targetRecord->Player)->first();
            }

            if ($targetRecord) {
                $target = new stdClass();
                $target->score = $targetRecord->Score;
                $target->cps = explode(',', $targetRecord->Checkpoints);
                $target->name = ml_escape($targetPlayer->NickName);

                self::$targets->put($player->id, $target);
                Template::show($player, 'cp-diffs.widget', compact('target'));
            }
        }
    }

    public static function cmdSetTarget(Player $player, $cmd, string $type = null, string $id = null)
    {
        if ($type === null) {
            warningMessage('Invalid target specified. See ', secondary('/help'),
                ' for more information.')->send($player);
            return;
        }

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

            $target = new stdClass();
            $target->score = $record->Score;
            $target->cps = explode(',', $record->Checkpoints);
            $target->name = ml_escape($targetPlayer->NickName);

            self::$targets->put($player->id, $target);
            Template::show($player, 'cp-diffs.widget', compact('target'));
        } else {
            warningMessage('Invalid target specified. See ', secondary('/help'),
                ' for more information.')->send($player);
        }
    }
}
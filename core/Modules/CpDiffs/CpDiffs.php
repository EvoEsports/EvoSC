<?php


namespace EvoSC\Modules\CpDiffs;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\MapController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
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

        ChatCommand::add('/pb', [self::class, 'printPersonalBestToChat']);

        if (isTrackmania()) {
            Server::triggerModeScriptEvent('Common.UIModules.SetProperties', [json_encode([
                'uimodules' => [
                    [
                        'id' => 'Race_BestRaceViewer',
                        'visible' => false,
                        'visible_update' => true
                    ]
                ]
            ])]);
        }
    }

    public static function requestCpDiffs(Player $player)
    {
        self::sendInitialCpDiff($player, MapController::getCurrentMap());
    }

    public static function beginMap()
    {
        self::$targets = collect();

        foreach (onlinePlayers() as $player) {
            self::printPersonalBestToChat($player);
        }
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

    public static function printPersonalBestToChat(Player $player)
    {
        $pb = DB::table('pbs')
            ->where('map_id', '=', MapController::getCurrentMap()->id)
            ->where('player_id', '=', $player->id)
            ->first();

        if ($pb) {
            $cps = collect(explode(',', $pb->checkpoints))->map(function ($cp, $key) {
                return '$666' . ($key + 1) . '|$fff' . formatScore(intval($cp), true);
            })->implode(', ');

            infoMessage('Your PB is ', secondary(formatScore($pb->score, true)), ', checkpoints: ', secondary($cps))->send($player);
        } else {
            infoMessage('You don\'t have a PB on this map/server yet.')->send($player);
        }
    }

    public static function sendInitialCpDiff(Player $player, Map $map)
    {
        $pb = DB::table('pbs')->where('map_id', '=', $map->id)->where('player_id', '=', $player->id)->first();

        if ($pb != null) {
            self::$targets->put($player->id, $target = self::createTarget($pb->score, $pb->checkpoints, $player->id, $map->uid));
            Template::show($player, 'CpDiffs.widget', compact('target'));
        } else {
            if (isManiaPlanet()) {
                $targetRecord = DB::table('dedi-records')->where('Map', '=', $map->id)->where('Player', '=',
                    $player->id)->first();
            }

            if (!isset($targetRecord) || is_null($targetRecord)) {
                $targetRecord = DB::table('local-records')->where('Map', '=', $map->id)->where('Player', '=',
                    $player->id)->first();
            }

            if ($targetRecord) {
                self::$targets->put($player->id, $target = self::createTarget($targetRecord->Score, $targetRecord->Checkpoints, $targetRecord->Player, $map->uid));
                Template::show($player, 'CpDiffs.widget', compact('target'));
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
                if (isTrackmania()) {
                    warningMessage('Selecting dedis as target is only available in TM².')->send($player);
                    return;
                }
                $record = DB::table('dedi-records')->where('Map', '=', $map->id)->where('Rank', '=', $id)->first();
                break;

            case 'me':
                self::sendInitialCpDiff($player, $map);
                return;
        }

        if (isset($record) && !is_null($record)) {
            self::$targets->put($player->id, $target = self::createTarget($record->Score, $record->Checkpoints, $record->Player, $map->uid));
            Template::show($player, 'CpDiffs.widget', compact('target'));
        } else {
            warningMessage('Invalid target specified. See ', secondary('/help'),
                ' for more information.')->send($player);
        }
    }

    /**
     * @param $record
     * @param $mapUid
     * @return mixed
     */
    private static function createTarget($score, $checkpoints, $playerId, $mapUid)
    {
        $targetPlayer = Player::whereId($playerId)->first();

        $target = new stdClass();
        $target->score = $score;
        $target->cps = explode(',', $checkpoints);
        $target->name = ml_escape($targetPlayer->NickName);
        $target->map_uid = $mapUid;

        return $target;
    }
}
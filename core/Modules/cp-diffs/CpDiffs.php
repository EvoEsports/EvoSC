<?php


namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Dedi;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class CpDiffs implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static $targets;

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);

        ChatCommand::add('/target', [self::class, 'cmdSetTarget'],
            'Use /target local|dedi|wr|me #id to load CPs of record to bottom widget');
    }

    public static function beginMap(Map $map)
    {
        self::$targets = collect();

        foreach (onlinePlayers() as $player) {
            self::sendInitialCpDiff($player, $map);
        }
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score == 0) {
            return;
        }

        if (self::$targets->has($player->id)) {
            if (self::$targets->get($player->id)->score <= $score) {
                return;
            }
        }

        $target = new \stdClass();
        $target->score = $score;
        $target->cps = explode(',', $checkpoints);
        $target->name = ml_escape($player->NickName);

        self::$targets->put($player->id, $target);
        Template::show($player, 'cp-diffs.widget', compact('target'));
    }

    public static function sendInitialCpDiff(Player $player, Map $map)
    {
        $pb = DB::table('pbs')->where('map_id', '=', $map->id)->where('player_id', '=', $player->id)->first();

        if ($pb) {
            $target = new \stdClass();
            $target->score = $pb->score;
            $target->cps = explode(',', $pb->checkpoints);
            $target->name = ml_escape($player->NickName);

            self::$targets->put($player->id, $target);
            Template::show($player, 'cp-diffs.widget', compact('target'));
        } else {
            $target = LocalRecord::whereMap($map->id)->wherePlayer($player->id)->first();

            if(!$target){
                $target = Dedi::whereMap($map->id)->wherePlayer($player->id)->first();
            }

            if(!$target){
                $target = Dedi::whereMap($map->id)->orderByDesc('Score')->first();
            }

            if ($target) {
                $target = new \stdClass();
                $target->score = $target->Score;
                $target->cps = explode(',', $target->Checkpoints);
                $target->name = ml_escape($target->player->NickName);

                self::$targets->put($player->id, $target);
                Template::show($player, 'cp-diffs.widget', compact('target'));
            }
        }
    }

    public static function cmdSetTarget(Player $player, string $cmd, string $type = null, string $id = null)
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

            $target = new \stdClass();
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
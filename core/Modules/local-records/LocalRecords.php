<?php

use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\HookController;
use esc\Controllers\MapController;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Database\Schema\Blueprint;

class LocalRecords
{
    static $checkpoints;

    /**
     * LocalRecords constructor.
     */
    public function __construct()
    {
        LocalRecords::createTables();

        self::$checkpoints = new \Illuminate\Support\Collection();

        include_once __DIR__ . '/Models/LocalRecord.php';

        Template::add('locals', File::get(__DIR__ . '/Templates/locals.latte.xml'));

        Hook::add('PlayerFinish', 'LocalRecords::playerFinish');
        Hook::add('BeginMap', 'LocalRecords::beginMap');
        Hook::add('PlayerConnect', 'LocalRecords::beginMap');
        Hook::add('PlayerCheckpoint', 'LocalRecords::playerCheckpoint');

        ManiaLinkEvent::add('locals.show', 'LocalRecords::showLocalsModal');
        ManiaLinkEvent::add('modal.hide', 'LocalRecords::hideLocalsModal');
    }

    /**
     * Create the database tables
     */
    public static function createTables()
    {
        Database::create('local-records', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('Player');
            $table->integer('Map');
            $table->integer('Score');
            $table->integer('Rank');
            $table->text('Checkpoints')->nullable();
        });
    }

    /**
     * Checks if player has local
     * @param Map $map
     * @param Player $player
     * @return bool
     */
    private static function playerHasLocal(Map $map, Player $player): bool
    {
        return LocalRecord::whereMap($map->id)->wherePlayer($player->id)->first() != null;
    }

    /**
     * Called @ PlayerCheckpoint
     * @param Player $player
     * @param int $time
     * @param int $curLap
     * @param int $cpId
     */
    public static function playerCheckpoint(Player $player, int $time, int $curLap, int $cpId)
    {
        $existingCpTime = self::$checkpoints->where('player.Login', $player->Login)->where('id', $cpId);
        if ($existingCpTime->isNotEmpty()) {
            self::$checkpoints = self::$checkpoints->diff($existingCpTime);
        }

        $cp = collect([]);
        $cp->player = $player;
        $cp->time = $time;
        $cp->id = $cpId;

        self::$checkpoints->push($cp);
    }

    /**
     * Get the best CPs of the player for this round
     * @param Player $player
     * @return string
     */
    public static function getBestCps(Player $player): string
    {
        return self::$checkpoints->where('player.Login', $player->Login)->pluck('time')->sortBy('time')->implode(',');
    }

    /**
     * Called @ PlayerFinish
     * @param Player $player
     * @param int $score
     */
    public static function playerFinish(Player $player, int $score)
    {
        if ($score == 0) {
            return;
        }

        $map = MapController::getCurrentMap();

        $localsCount = $map->locals()->count();

        if (self::playerHasLocal($map, $player)) {
            $local = $map->locals()->wherePlayer($player->id)->first();

            if ($score == $local->Score) {
                ChatController::messageAll('Player ', $player, ' equaled his/hers ', $local);
                return;
            }

            if ($score < $local->Score) {
                $diff = $local->Score - $score;
                $rank = self::getRank($map, $score);

                if ($rank != $local->Rank) {
                    self::pushDownRanks($map, $rank);
                    $local->update(['Score' => $score, 'Rank' => $rank, 'Checkpoints' => self::getBestCps($player)]);
                    self::fixLocalRecordRanks($map);
                    $local = $map->locals()->wherePlayer($player->id)->first();

                    ChatController::messageAll('Player ', $player, ' gained the ', $local, ' (-' . formatScore($diff) . ')');
                } else {
                    $local->update(['Score' => $score, 'Checkpoints' => self::getBestCps($player)]);
                    self::fixLocalRecordRanks($map);
                    $local = $map->locals()->wherePlayer($player->id)->first();

                    ChatController::messageAll('Player ', $player, ' secured his/hers ', $local, ' (-' . formatScore($diff) . ')');
                }
            }
        } else {
            if ($localsCount < 100) {
                $worstLocal = $map->locals()->orderByDesc('Score')->first();

                if ($worstLocal) {
                    if ($score <= $worstLocal->Score) {
                        self::pushDownRanks($map, $worstLocal->Rank);
                        self::pushLocal($map, $player, $score, $worstLocal->Rank);
                        self::fixLocalRecordRanks($map);
                        $local = $map->locals()->wherePlayer($player->id)->first();
                        ChatController::messageAll('Player ', $player, ' gained the ', $local);
                    } else {
                        self::pushLocal($map, $player, $score, $worstLocal->Rank + 1);
                        self::fixLocalRecordRanks($map);
                        $local = $map->locals()->wherePlayer($player->id)->first();
                        ChatController::messageAll('Player ', $player, ' made the ', $local);
                    }
                } else {
                    $rank = 1;
                    $local = self::pushLocal($map, $player, $score, $rank);
                    ChatController::messageAll('Player ', $player, ' made the ', $local);
                }
            }
        }

        self::displayLocalRecords();
    }

    /**
     * Insert local into database
     * @param Map $map
     * @param Player $player
     * @param int $score
     * @param int $rank
     * @return LocalRecord
     */
    private static function pushLocal(Map $map, Player $player, int $score, int $rank): LocalRecord
    {
        $map->locals()->create([
            'Player' => $player->id,
            'Map' => $map->id,
            'Score' => $score,
            'Rank' => $rank,
            'Checkpoints' => self::getBestCps($player)
        ]);

        self::fixLocalRecordRanks($map);

        $local = $map->locals()->wherePlayer($player->id)->first();

        HookController::call('PlayerLocal', [$player, $local]);

        return $local;
    }

    private static function fixLocalRecordRanks(Map $map)
    {
        //Fix locals rank order
        foreach ($map->locals->sortBy('Score') as $key => $local) {
            $local->update(['Rank' => $key + 1]);
        }
    }

    /**
     * Push down ranks from given position
     * @param Map $map
     * @param int $startRank
     */
    private static function pushDownRanks(Map $map, int $startRank)
    {
        $map->locals()->where('Rank', '>=', $startRank)->orderByDesc('Rank')->increment('Rank');
    }

    /**
     * Get rank for driven time
     * @param Map $map
     * @param int $score
     * @return int|null
     */
    private static function getRank(Map $map, int $score): ?int
    {
        $nextBetter = $map->locals->where('Score', '<=', $score)->sortByDesc('Score')->first();

        if ($nextBetter) {
            return $nextBetter->Rank + 1;
        }

        return 1;
    }

    /**
     * Called @ BeginMap
     */
    public static function beginMap()
    {
        self::$checkpoints = new \Illuminate\Support\Collection();
        self::displayLocalRecords();
    }

    /**
     * Display the locals overview
     * @param Player $player
     */
    public static function showLocalsModal(Player $player)
    {
        $map = MapController::getCurrentMap();
        $chunks = $map->locals->chunk(25);

        $columns = [];
        foreach ($chunks as $key => $chunk) {
            $ranking = Template::toString('esc.ranking', ['ranks' => $chunk]);
            array_push($columns, '<frame pos="' . ($key * 45) . ' 0" scale="0.8">' . $ranking . '</frame>');
        }

        Template::show($player, 'esc.modal', [
            'id' => 'LocalRecordsOverview',
            'width' => 180,
            'height' => 97,
            'content' => implode('', $columns)
        ]);
    }

    /**
     * Hide locals overview
     * @param Player $player
     * @param string $id
     */
    public static function hideLocalsModal(Player $player, string $id)
    {
        Template::hide($player, $id);
    }

    /**
     * Display locals widget
     */
    public static function displayLocalRecords()
    {
        $locals = MapController::getCurrentMap()
            ->locals()
            ->orderBy('Rank')
            ->get()
            ->take(config('ui.locals.rows'));

        Template::showAll('esc.box', [
            'id' => 'Locals',
            'title' => '🏆  LOCAL RECORDS',
            'x' => config('ui.locals.x'),
            'y' => config('ui.locals.y'),
            'rows' => config('ui.locals.rows'),
            'scale' => config('ui.locals.scale'),
            'content' => Template::toString('locals', ['locals' => $locals]),
            'action' => 'locals.show'
        ]);
    }
}
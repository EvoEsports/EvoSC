<?php

namespace esc\Modules\LocalRecords;

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
    /**
     * LocalRecords constructor.
     */
    public function __construct()
    {
        LocalRecords::createTables();

        include_once __DIR__ . '/Models/LocalRecord.php';

        Template::add('locals', File::get(__DIR__ . '/Templates/locals.latte.xml'));

        Hook::add('PlayerFinish', 'LocalRecords::playerFinish');
        Hook::add('BeginMap', 'LocalRecords::beginMap');
        Hook::add('PlayerConnect', 'LocalRecords::beginMap');

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
        return $map->locals()->wherePlayer($player->id)->get()->first() != null;
    }

    /**
     * Called @ PlayerFinish
     * @param Player $player
     * @param int $score
     */
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $map = MapController::getCurrentMap();

        $localCount = $map->locals()->count();

        $local = $map->locals()->wherePlayer($player->id)->first();
        if ($local != null) {
            if ($score == $local->Score) {
                ChatController::messageAll('_local', 'Player ', $player, ' equaled his/her ', $local);
                return;
            }

            $oldRank = $local->Rank;

            if ($score < $local->Score) {
                $diff = $local->Score - $score;
                $local->update(['Score' => $score, 'Checkpoints' => $checkpoints]);
                $local = self::fixLocalRecordRanks($map, $player);

                if ($oldRank == $local->Rank) {
                    ChatController::messageAll('_local', 'Player ', $player, ' secured his/her ', $local, ' (-' . formatScore($diff) . ')');
                } else {
                    ChatController::messageAll('_local', 'Player ', $player, ' gained the ', $local, ' (-' . formatScore($diff) . ')');
                }
                HookController::call('PlayerLocal', [$player, $local]);
                self::displayLocalRecords();
            }
        } else {
            if ($localCount < 100) {
                $map->locals()->create([
                    'Player' => $player->id,
                    'Map' => $map->id,
                    'Score' => $score,
                    'Checkpoints' => $checkpoints,
                    'Rank' => 999,
                ]);
                $local = self::fixLocalRecordRanks($map, $player);
                ChatController::messageAll('_local', 'Player ', $player, ' claimed the ', $local);
                HookController::call('PlayerLocal', [$player, $local]);
                self::displayLocalRecords();
            }
        }
    }

    /**
     * Fix local ranks
     * @param Map $map
     * @param Player|null $player
     * @return null
     */
    private static function fixLocalRecordRanks(Map $map, Player $player = null)
    {
        $locals = $map->locals()->orderBy('Score')->get();
        $i = 1;
        foreach ($locals as $local) {
            $local->update(['Rank' => $i]);
            $i++;
        }

        if ($player) {
            return $map->locals()->wherePlayer($player->id)->first();
        }

        return null;
    }

    /**
     * Called @ BeginMap
     */
    public static function beginMap()
    {
        self::displayLocalRecords();
    }

    /**
     * Display the locals overview
     * @param Player $player
     */
    public static function showLocalsModal(Player $player)
    {
        $map = MapController::getCurrentMap();
        $chunks = $map->locals()->orderBy('Score')->get()->chunk(25);

        $columns = [];
        foreach ($chunks as $key => $chunk) {
            $ranking = Template::toString('esc.ranking', ['ranks' => $chunk]);
            array_push($columns, '<frame pos="' . ($key * 45) . ' 0" scale="0.8">' . $ranking . '</frame>');
        }

        Template::show($player, 'esc.modal', [
            'id' => 'LocalRecordsOverview',
            'width' => 180,
            'height' => 97,
            'content' => implode('', $columns),
            'showAnimation' => true
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

        onlinePlayers()->each(function (Player $player) use ($locals) {
            $hideScript = Template::toString('esc.hide-script', ['hideSpeed' => $player->user_settings->ui->hideSpeed ?? null, 'config' => config('ui.locals')]);

            Template::show($player, 'esc.box', [
                'id' => 'local-records',
                'title' => '🏆  LOCAL RECORDS',
                'config' => config('ui.locals'),
                'hideScript' => $hideScript,
                'rows' => config('ui.locals.rows'),
                'scale' => config('ui.locals.scale'),
                'content' => Template::toString('locals', compact('locals')),
                'action' => 'locals.show'
            ]);
        });
    }
}
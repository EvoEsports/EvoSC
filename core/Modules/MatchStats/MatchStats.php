<?php


namespace EvoSC\Modules\MatchStats;


use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Controllers\MapController;
use EvoSC\Interfaces\ModuleInterface;

class MatchStats extends Module implements ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('Scores', [self::class, 'scoresUpdated']);
    }

    /**
     * @param \stdClass $scores
     * @return void
     * @throws \Throwable
     */
    public static function scoresUpdated(\stdClass $scores)
    {
        if ($scores->section == 'EndRound' || $scores->section == 'EndMatch') {
            DB::transaction(function () use ($scores) {
                $mapUid = MapController::getCurrentMap()->uid;
                $teams = collect($scores->teams)->keyBy('id');
                $time = now()->toDateTimeString();

                foreach ($scores->players as $player) {
                    $team = null;
                    if ($player->team != -1) {
                        $team = $teams->get($player->team)->name;
                    }

                    DB::table('match_stats')->insert([
                        'map_uid'      => $mapUid,
                        'login'        => $player->login,
                        'ubiname'      => $player->name,
                        'team'         => $team,
                        'round'        => MapController::getMatchRound(),
                        'total_points' => $player->matchpoints,
                        'score'        => $player->prevracetime,
                        'checkpoints'  => implode(',', $player->prevracecheckpoints),
                        'position'     => $player->rank,
                        'end_match'    => $scores->section == 'EndMatch' ? 1 : 0,
                        'time'         => $time
                    ]);
                }
            });
        }
    }
}
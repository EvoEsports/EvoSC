<?php

namespace EvoSC\Modules\LiveRankings;

use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Controllers\PointsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class LiveRankings extends Module implements ModuleInterface
{
    private static $shownLogins = [];

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerInfoChanged', [self::class, 'checkIfViewIsAffected']);
        Hook::add('PlayerDisconnect', [self::class, 'checkIfViewIsAffected']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('Scores', [self::class, 'updateWidget']);

        if (ModeController::isTimeAttackType()) {
            Hook::add('PlayerFinish', function ($player, $score) {
                if ($score > 0) {
                    Server::callGetScores();
                }
            });
        }

        if (!$isBoot) {
            Template::showAll('LiveRankings.widget', ['originalPointsLimit' => PointsController::getOriginalPointsLimit()]);
        }
    }

    /**
     * @param $scores
     */
    public static function updateWidget($scores)
    {
        $playerScores = collect($scores->players);

        if (ModeController::isTimeAttackType()) {
            $playerScores = $playerScores->sortBy('bestracetime')->filter(function ($playerScore) {
                return $playerScore->bestracetime > 0;
            });
        } else {
            $playerScores = $playerScores->sortByDesc('matchpoints')->filter(function ($playerScore) {
                return $playerScore->matchpoints > 0;
            });
        }

        $playerScores = $playerScores->take(config('live-rankings.show', 14));
        self::$shownLogins = $playerScores->pluck('login')->toArray();

        $playerInfo = DB::table('players')
            ->select(['Login', 'NickName', 'player_id', 'spectator_status'])
            ->whereIn('Login', $playerScores->pluck('login'))
            ->get()
            ->keyBy('Login');

        $top = $playerScores->map(function ($playerScore) use ($playerInfo) {
            $info = $playerInfo->get($playerScore->login);

            return [
                'name' => $info->NickName,
                'login' => $playerScore->login,
                'points' => $playerScore->matchpoints,
                'gained' => $playerScore->roundpoints,
                'score' => $playerScore->bestracetime,
                'team' => $playerScore->team,
                'checkpoints' => '',
                'online' => $info->player_id > 0,
                'spectator' => $info->spectator_status > 0,
            ];
        })->values();

        Template::showAll('LiveRankings.update', compact('top'));
    }

    /**
     * @param Player $player
     */
    public static function checkIfViewIsAffected(Player $player)
    {
        if (in_array($player->Login, self::$shownLogins)) {
            Server::callGetScores();
        }
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function playerConnect(Player $player)
    {
        Server::callGetScores();
        $originalPointsLimit = PointsController::getOriginalPointsLimit();
        Template::show($player, 'LiveRankings.widget', compact('originalPointsLimit'));
    }
}
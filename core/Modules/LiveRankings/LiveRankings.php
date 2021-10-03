<?php

namespace EvoSC\Modules\LiveRankings;

use EvoSC\Classes\Cache;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Controllers\PointsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;

class LiveRankings extends Module implements ModuleInterface
{
    private static $shownLogins = [];
    private static $lapTracker = [];
    private static $sectionTracker = [];
    private static $numberOfLaps = -1;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (ModeController::isTimeAttackType()) {
            if (ModeController::isRoyal()) {
                if (ModeController::getMode() != 'Trackmania/Evo_Royal_TA.Script.txt') {
                    Log::warning("For local records to work in RoyalTA, you need to download Evos modified RoyalTA script from https://raw.githubusercontent.com/EvoTM/Evo-Royal-TA/master/Evo_Royal_TA.Script.txt.\nSave it to UserData/Scripts/Modes/Trackmania and set 'Trackmania/Evo_Royal_TA.Script.txt' in your match-settings.");

                    return;
                }

                Hook::add('PlayerFinishSegment', [self::class, 'playerFinishSection']);
                Hook::add('BeginMatch', [self::class, 'resetLapsTracker']);
                Hook::add('EndMap', [self::class, 'resetLapsTracker']);
            } else {
                Hook::add('Scores', [self::class, 'updateWidget']);
                Hook::add('PlayerFinish', function ($player, $score) {
                    if ($score > 0) {
                        Server::callGetScores();
                    }
                });
            }
        } else if (ModeController::laps()) {
            Hook::add('PlayerFinish', [self::class, 'playerLap']);
            Hook::add('PlayerLap', [self::class, 'playerLap']);
            Hook::add('BeginMatch', [self::class, 'resetLapsTracker']);
            Hook::add('EndMap', [self::class, 'resetLapsTracker']);

            self::$numberOfLaps = Server::getCurrentMapInfo()->nbLaps;
        } else {
            Hook::add('Scores', [self::class, 'updateWidget']);
        }

        Hook::add('PlayerInfoChanged', [self::class, 'checkIfViewIsAffected']);
        Hook::add('PlayerDisconnect', [self::class, 'checkIfViewIsAffected']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);

        if (!$isBoot) {
            Template::showAll('LiveRankings.widget', ['originalPointsLimit' => PointsController::getOriginalPointsLimit()]);
        }
    }

    /**
     * @throws \Exception
     */
    public function stop()
    {
        if (ModeController::isRoyal()) {
            Cache::put('live_ranking_sections', self::$sectionTracker, now()->addMinute());
        }
    }

    public static function resetLapsTracker()
    {
        self::$lapTracker = [];
        self::$sectionTracker = [];
        self::updateWidget(collect());
    }

    public static function playerFinishSection(Player $player, int $score, int $segment, int $totalSegments)
    {
        dump($totalSegments);

        $id = $player->id;
        if (!array_key_exists($id, self::$sectionTracker)) {
            self::$sectionTracker[$id] = (object)[
                'laps'         => 0,
                'login'        => $player->Login,
                'matchpoints'  => 0,
                'roundpoints'  => 0,
                'bestracetime' => 0,
                'team'         => 0,
                'section'      => 0,
            ];
        }

        self::$sectionTracker[$id]->section = $totalSegments;
        self::$sectionTracker[$id]->bestracetime = $score;
        self::updateWidget(collect(self::$sectionTracker));
    }

    public static function playerLap(Player $player, $score, $checkpointsString)
    {
        $id = $player->id;
        if (!array_key_exists($id, self::$lapTracker)) {
            self::$lapTracker[$id] = (object)[
                'laps'         => 0,
                'login'        => $player->Login,
                'matchpoints'  => 0,
                'roundpoints'  => 0,
                'bestracetime' => 0,
                'team'         => 0,
                'section'      => 0,
            ];
        }

        self::$lapTracker[$id]->laps++;
        self::updateWidget(collect(self::$lapTracker));
    }

    /**
     * @param $scores
     */
    public static function updateWidget($scores)
    {
        if ($scores instanceof Collection) {
            $playerScores = $scores;
        } else {
            $playerScores = collect($scores->players);
        }

        if (ModeController::isTimeAttackType()) {
            if (ModeController::isRoyal()) {
                $playerScores = $playerScores->filter(function ($playerScore) {
                    return $playerScore->section > 0;
                })->sortByDesc('section');
            } else {
                $playerScores = $playerScores->filter(function ($playerScore) {
                    return $playerScore->bestracetime > 0;
                })->sortBy('bestracetime');
            }
        } else {
            if (ModeController::laps()) {
                $playerScores = $playerScores->sortByDesc('laps')->filter(function ($playerScore) {
                    return ($playerScore->laps ?? 0) > 0;
                });
            } else {
                $playerScores = $playerScores->sortByDesc('matchpoints')->filter(function ($playerScore) {
                    return $playerScore->matchpoints > 0;
                });
            }
        }

        $playerScores = $playerScores->take(config('live - rankings . show', 14));
        self::$shownLogins = $playerScores->pluck('login')->toArray();

        $playerInfo = DB::table('players')
            ->select(['Login', 'NickName', 'player_id', 'spectator_status'])
            ->whereIn('Login', $playerScores->pluck('login'))
            ->get()
            ->keyBy('Login');

        $top = $playerScores->map(function ($playerScore) use ($playerInfo) {
            $info = $playerInfo->get($playerScore->login);

            return [
                'name'        => $info->NickName,
                'login'       => $playerScore->login,
                'points'      => $playerScore->matchpoints,
                'gained'      => $playerScore->roundpoints,
                'score'       => $playerScore->bestracetime,
                'team'        => $playerScore->team,
                'laps'        => $playerScore->laps,
                'section'     => $playerScore->section,
                'checkpoints' => '',
                'online'      => $info->player_id > 0,
                'spectator'   => $info->spectator_status > 0,
            ];
        })->values();

        Template::showAll('LiveRankings.update', compact('top'));
    }

    /**
     * @param Player $player
     */
    public static function checkIfViewIsAffected(Player $player)
    {
        if (ModeController::isRoyal()) {
            self::updateWidget(collect(self::$sectionTracker));
        } else {
            if (in_array($player->Login, self::$shownLogins)) {
                Server::callGetScores(); //Force server to send scores callback
            }
        }
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function playerConnect(Player $player)
    {
        $originalPointsLimit = -1;
        $nbLaps = -1;

        if (ModeController::laps()) {
            $nbLaps = self::$numberOfLaps; //TODO: Check why S_ForceLapsNb does not have any effect and send correct value here instead
            self::updateWidget(collect(self::$lapTracker));
        } else {
            Server::callGetScores();
            if (ModeController::teams()) {
                $originalPointsLimit = PointsController::getOriginalPointsLimit();
            }
        }

        Template::show($player, 'LiveRankings.widget', compact('originalPointsLimit', 'nbLaps'));
    }
}
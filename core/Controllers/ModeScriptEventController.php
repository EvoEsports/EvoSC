<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Interfaces\ControllerInterface;

/**
 * Class ModeScriptEventController
 *
 * @package EvoSC\Controllers
 */
class ModeScriptEventController implements ControllerInterface
{
    /**
     * Initialize ModeScriptEventController
     */
    public static function init()
    {
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
    }

    /**
     * Process mode-script-callbacks (send from mode-script, not dedicated -> callback list in docs does not apply).
     *
     * @param $modescriptCallbackArray
     */
    public static function handleModeScriptCallbacks($modescriptCallbackArray)
    {
        if ($modescriptCallbackArray[0] == 'ManiaPlanet.ModeScriptCallbackArray') {
            self::call($modescriptCallbackArray[1][0], $modescriptCallbackArray[1][1]);
        } else {
            Log::write('Modescript callback is not ManiaPlanet.ModeScriptCallbackArray', isVerbose());
            Log::write(serialize($modescriptCallbackArray), isVeryVerbose());
        }
    }

    //Decide if the callback should be transformed and fire the hooks.

    /**
     * @param $callback
     * @param $arguments
     */
    private static function call($callback, $arguments)
    {
        switch ($callback) {
            case 'Trackmania.Scores':
                self::tmScores($arguments);

                return;

            case 'Trackmania.Event.GiveUp':
                self::tmGiveUp($arguments);

                return;

            case 'Trackmania.Event.WayPoint':
                self::tmWayPoint($arguments);

                return;

            case 'Trackmania.Event.StartCountdown':
                self::tmStartCountdown($arguments);

                return;

            case 'Trackmania.Event.StartLine':
                self::tmStartLine($arguments);

                return;

            case 'Trackmania.WarmUp.End':
                Hook::fire('WarmUpEnd');
                break;

            case 'Trackmania.WarmUp.Start':
                Hook::fire('WarmUpStart');
                break;

            case 'Trackmania.PointsRepartition':
                Hook::fire('PointsRepartition', json_decode($arguments[0])->pointsrepartition);
                break;

            case 'Royal.PlayerFinish':
                $data = json_decode($arguments[0]);
                Hook::fire('PlayerFinish', player($data->login), $data->racetime, '');
                break;

            case 'Royal.PlayerFinishSegment':
                $data = json_decode($arguments[0]);
                Hook::fire('PlayerFinishSegment', player($data->login), $data->racetime, $data->segment, $data->totalsegments);
                break;

            case 'Maniaplanet.EndRound_Start':
            case 'Maniaplanet.StartMap_Start':
            case 'Maniaplanet.EndMap_Start':
                Hook::fire($callback);

                return;

            default:
                Hook::fire($callback, $arguments);
                if (isVeryVerbose()) {
                    Log::write('Calling unmapped ' . $callback, true);
                    if (isDebug()) {
                        var_dump($arguments);
                    }
                }
        }
    }

    /**
     * Called on round end, when scores show.
     *
     * @param $arguments
     */
    static function tmScores($arguments)
    {
        if (count($arguments) == 1) {
            $scores = json_decode($arguments[0]);

            if ($scores->section == 'EndMap') {
                if ($scores->winnerplayer != '') {
                    Hook::fire('AnnounceWinner', player($scores->winnerplayer));
                }
                Hook::fire('ShowScores', collect($scores->players));
            }

            Hook::fire('Scores', $scores);
        }
    }

    /**
     * Called when a player resets his car.
     *
     * @param $arguments
     */
    static function tmGiveUp($arguments)
    {
        $data = json_decode($arguments[0]);
        $player = player($data->login);

        Hook::fire('PlayerFinish', $player, 0, "");
        Hook::fire('PlayerGiveUp', $player);
    }

    /**
     * Called when a player passes a checkpoint or the finish (last checkpoint).
     *
     * @param $arguments
     */
    static function tmWayPoint($arguments)
    {
        $wayPoint = json_decode($arguments[0]);
        $player = player($wayPoint->login);

        //checkpoint passed
        Hook::fire('PlayerCheckpoint',
            $player,
            $wayPoint->laptime,
            $wayPoint->checkpointinlap,
            $wayPoint->isendlap
        );

        //player finished
        if ($wayPoint->isendrace || (!ModeController::laps() && $wayPoint->isendlap)) {
            if (!ModeController::isRoyal()) {
                Hook::fire('PlayerFinish',
                    $player,
                    $wayPoint->laptime,
                    implode(',', $wayPoint->curlapcheckpoints)
                );
            }
        } else if ($wayPoint->isendlap) {
            Hook::fire('PlayerLap',
                $player,
                $wayPoint->laptime,
                implode(',', $wayPoint->curlapcheckpoints)
            );
        }
    }

    /**
     * Called when the countdown starts for a player.
     *
     * @param $arguments
     */
    static function tmStartCountdown($arguments)
    {
        $playerLogin = json_decode($arguments[0])->login;
        Hook::fire('PlayerStartCountdown', player($playerLogin));
    }

    /**
     * Called when the car of the players gets unfrozen.
     *
     * @param $arguments
     */
    static function tmStartLine($arguments)
    {
        $player = player(json_decode($arguments[0])->login);
        Hook::fire('PlayerStartLine', $player);
    }
}
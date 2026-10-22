<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class GameInfos extends AbstractStructure
{
    /**
     * Game Modes
     */
    const GAMEMODE_SCRIPT = 0;
    const GAMEMODE_ROUNDS = 1;
    const GAMEMODE_TIMEATTACK = 2;
    const GAMEMODE_TEAM = 3;
    const GAMEMODE_LAPS = 4;
    const GAMEMODE_CUP = 5;
    const GAMEMODE_STUNTS = 6;

    /** @var int */
    public $gameMode;
    /** @var string */
    public $scriptName;
    /** @var int */
    public $nbMaps;
    /** @var int */
    public $chatTime;
    /** @var int */
    public $finishTimeout;
    /** @var int */
    public $allWarmUpDuration;
    /** @var bool */
    public $disableRespawn;
    /** @var int */
    public $forceShowAllOpponents;
    /** @var int */
    public $roundsPointsLimit;
    /** @var int */
    public $roundsForcedLaps;
    /** @var bool */
    public $roundsUseNewRules;
    /** @var int */
    public $roundsPointsLimitNewRules;
    /** @var int */
    public $teamPointsLimit;
    /** @var int */
    public $teamMaxPoints;
    /** @var bool */
    public $teamUseNewRules;
    /** @var int */
    public $teamPointsLimitNewRules;
    /** @var int */
    public $timeAttackLimit;
    /** @var int */
    public $timeAttackSynchStartPeriod;
    /** @var int */
    public $lapsNbLaps;
    /** @var int */
    public $lapsTimeLimit;
    /** @var int */
    public $cupPointsLimit;
    /** @var int */
    public $cupRoundsPerMap;
    /** @var int */
    public $cupNbWinners;
    /** @var int */
    public $cupWarmUpDuration;
}

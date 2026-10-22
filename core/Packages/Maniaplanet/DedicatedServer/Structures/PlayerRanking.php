<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class PlayerRanking extends Player
{
    /** @var string */
    public $nickName;
    /** @var int */
    public $playerId;
    /** @var int */
    public $rank;
    /** @var int */
    public $bestTime;
    /** @var int[] */
    public $bestCheckpoints;
    /** @var int */
    public $score;
    /** @var int */
    public $nbrLapsFinished;
    /** @var float */
    public $ladderScore;
}

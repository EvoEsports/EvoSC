<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class LadderStats extends AbstractStructure
{
    /** @var float */
    public $lastMatchScore;
    /** @var int */
    public $nbrMatchWins;
    /** @var int */
    public $nbrMatchDraws;
    /** @var int */
    public $nbrMatchLosses;
    /** @var string */
    public $teamName;
    /** @var ZoneRanking[] */
    public $playerRankings;
    /** @var array */
    public $teamRankings;

    /**
     * @return LadderStats
     */
    static function fromArray($array)
    {
        $object = parent::fromArray($array);
        $object->playerRankings = ZoneRanking::fromArrayOfArray($object->playerRankings);
        return $object;
    }
}

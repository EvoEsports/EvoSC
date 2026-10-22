<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class ZoneRanking extends AbstractStructure
{
    /** @var string */
    public $path;
    /** @var float */
    public $score;
    /** @var int */
    public $ranking;
    /** @var int */
    public $totalCount;

    /**
     * @return string[]
     */
    function getArrayFromPath()
    {
        return explode('|', $this->path);
    }
}

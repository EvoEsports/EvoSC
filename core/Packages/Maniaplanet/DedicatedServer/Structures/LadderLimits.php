<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class LadderLimits extends AbstractStructure
{
    /** @var float */
    public $ladderServerLimitMin;
    /** @var float */
    public $ladderServerLimitMax;
}

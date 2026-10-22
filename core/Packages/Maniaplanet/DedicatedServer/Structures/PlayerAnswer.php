<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class PlayerAnswer extends Player
{
    /** @var int */
    public $playerId;
    /** @var int */
    public $result;
}

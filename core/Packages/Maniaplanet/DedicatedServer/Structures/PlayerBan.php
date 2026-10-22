<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class PlayerBan extends Player
{
    /** @var string */
    public $clientName;
    /** @var string */
    public $iPAddress;
}

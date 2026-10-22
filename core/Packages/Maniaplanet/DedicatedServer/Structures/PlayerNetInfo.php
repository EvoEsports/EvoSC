<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class PlayerNetInfo extends Player
{
    /** @var string */
    public $iPAddress;
    /** @var int */
    public $stateUpdateLatency;
    /** @var int */
    public $stateUpdatePeriod;
    /** @var int */
    public $latestNetworkActivity;
    /** @var float */
    public $packetLossRate;
}

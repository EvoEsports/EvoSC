<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class NetworkStats extends AbstractStructure
{
    /** @var int */
    public $uptime;
    /** @var int */
    public $nbrConnection;
    /** @var int */
    public $meanConnectionTime;
    /** @var int */
    public $meanNbrPlayer;
    /** @var int */
    public $recvNetRate;
    /** @var int */
    public $sendNetRate;
    /** @var int */
    public $totalReceivingSize;
    /** @var int */
    public $totalSendingSize;
    /** @var PlayerNetInfo[] */
    public $playerNetInfos;

    public static function fromArray($array)
    {
        $object = parent::fromArray($array);
        $object->playerNetInfos = PlayerNetInfo::fromArrayOfArray($object->playerNetInfos);
        return $object;
    }
}

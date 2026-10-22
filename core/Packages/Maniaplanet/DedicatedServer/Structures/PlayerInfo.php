<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class PlayerInfo extends Player
{
    /** @var string */
    public $nickName;
    /** @var int */
    public $playerId;
    /** @var int */
    public $teamId;
    /** @var bool */
    public $isSpectator;
    /** @var bool */
    public $isInOfficialMode;
    /** @var int */
    public $ladderRanking;
    /** @var int */
    public $spectatorStatus;
    /** @var int */
    public $flags;

    //Flags details
    /** @var int */
    public $forceSpectator;
    /** @var bool */
    public $isReferee;
    /** @var bool */
    public $isPodiumReady;
    /** @var bool */
    public $isUsingStereoscopy;
    /** @var bool */
    public $isManagedByAnOtherServer;
    /** @var bool */
    public $isServer;
    /** @var bool */
    public $hasPlayerSlot;
    /** @var bool */
    public $isBroadcasting;
    /** @var bool */
    public $hasJoinedGame;

    //SpectatorStatus details
    /** @var bool */
    public $spectator;
    /** @var bool */
    public $temporarySpectator;
    /** @var bool */
    public $pureSpectator;
    /** @var bool */
    public $autoTarget;
    /** @var int */
    public $currentTargetId;

    /**
     * @return PlayerInfo
     */
    public static function fromArray($array)
    {
        $object = parent::fromArray($array);

        //Detail flags
        $object->forceSpectator = $object->flags % 10; // 0, 1 or 2
        $object->isReferee = (bool)(intval($object->flags / 10) % 10);
        $object->isPodiumReady = (bool)(intval($object->flags / 100) % 10);
        $object->isUsingStereoscopy = (bool)(intval($object->flags / 1000) % 10);
        $object->isManagedByAnOtherServer = (bool)(intval($object->flags / 10000) % 10);
        $object->isServer = (bool)(intval($object->flags / 100000) % 10);
        $object->hasPlayerSlot = (bool)(intval($object->flags / 1000000) % 10);
        $object->isBroadcasting = (bool)(intval($object->flags / 10000000) % 10);
        $object->hasJoinedGame = (bool)(intval($object->flags / 100000000) % 10);
        //Details spectatorStatus
        $object->spectator = (bool)($object->spectatorStatus % 10);
        $object->temporarySpectator = (bool)(intval($object->spectatorStatus / 10) % 10);
        $object->pureSpectator = (bool)(intval($object->spectatorStatus / 100) % 10);
        $object->autoTarget = (bool)(intval($object->spectatorStatus / 1000) % 10);
        $object->currentTargetId = intval($object->spectatorStatus / 10000);

        return $object;
    }
}

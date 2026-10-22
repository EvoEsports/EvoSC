<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class PlayerDetailedInfo extends Player
{
    /** @var string */
    public $nickName;
    /** @var int */
    public $playerId;
    /** @var int */
    public $teamId;
    /** @var string */
    public $path;
    /** @var string */
    public $language;
    /** @var string */
    public $clientVersion;
    /** @var string */
    public $clientTitleVersion;
    /** @var string */
    public $iPAddress;
    /** @var int */
    public $downloadRate;
    /** @var int */
    public $uploadRate;
    /** @var bool */
    public $isSpectator;
    /** @var bool */
    public $isInOfficialMode;
    /** @var bool */
    public $isReferee;
    /** @var FileDesc */
    public $avatar;
    /** @var Skin[] */
    public $skins;
    /** @var LadderStats */
    public $ladderStats;
    /** @var int */
    public $hoursSinceZoneInscription;
    /** @var string */
    public $broadcasterLogin;
    /** @var string[] */
    public $allies = array();
    /** @var string */
    public $clubLink;

    /**
     * @return PlayerDetailedInfo
     */
    public static function fromArray($array)
    {
        $object = parent::fromArray($array);
        $object->avatar = FileDesc::fromArray($object->avatar);
        $object->skins = Skin::fromArrayOfArray($object->skins);
        $object->ladderStats = LadderStats::fromArray($object->ladderStats);
        return $object;
    }

    /**
     * @return string[]
     */
    function getArrayFromPath()
    {
        return explode('|', $this->path);
    }
}

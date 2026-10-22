<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class ServerOptions extends AbstractStructure
{
    /** @var string */
    public $name;
    /** @var string */
    public $comment;
    /** @var string */
    public $password;
    /** @var string */
    public $passwordForSpectator;
    /** @var int */
    public $hideServer;
    /** @var int */
    public $currentMaxPlayers;
    /** @var int */
    public $nextMaxPlayers;
    /** @var int */
    public $currentMaxSpectators;
    /** @var int */
    public $nextMaxSpectators;
    /** @var bool */
    public $isP2PUpload;
    /** @var bool */
    public $isP2PDownload;
    /** @var bool */
    public $currentLadderMode;
    /** @var int */
    public $nextLadderMode;
    /** @var float */
    public $ladderServerLimitMax;
    /** @var float */
    public $ladderServerLimitMin;
    /** @var int */
    public $currentVehicleNetQuality;
    /** @var int */
    public $nextVehicleNetQuality;
    /** @var int */
    public $currentCallVoteTimeOut;
    /** @var int */
    public $nextCallVoteTimeOut;
    /** @var float */
    public $callVoteRatio;
    /** @var bool */
    public $allowMapDownload;
    /** @var bool */
    public $autoSaveReplays;
    /** @var bool */
    public $autoSaveValidationReplays;
    /** @var string */
    public $refereePassword;
    /** @var int */
    public $refereeMode;
    /** @var bool */
    public $currentUseChangingValidationSeed;
    /** @var bool */
    public $nextUseChangingValidationSeed;
    /** @var int */
    public $clientInputsMaxLatency;
    /** @var bool */
    public $keepPlayerSlots;
    /** @var bool */
    public $disableHorns;
    /** @var bool */
    public $disableServiceAnnounces;

    /**
     * @internal
     * @return bool
     */
    function isValid()
    {
        return is_string($this->name)
            && is_string($this->comment)
            && is_string($this->password)
            && is_string($this->passwordForSpectator)
            && is_int($this->nextCallVoteTimeOut)
            && VoteRatio::isRatio($this->callVoteRatio);
    }

    /**
     * @internal
     * @return mixed[]
     */
    function toSetterArray()
    {
        $out = array();
        foreach (get_object_vars($this) as $key => $value) {
            if (substr($key, 0, 7) == 'current' || $value === null) {
                continue;
            }
            if ($key == 'nextUseChangingValidationSeed') {
                $key = 'useChangingValidationSeed';
            }
            $out[ucfirst($key)] = $value;
        }
        return $out;
    }
}

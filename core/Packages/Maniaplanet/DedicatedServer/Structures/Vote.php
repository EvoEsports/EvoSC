<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class Vote extends AbstractStructure
{
    const STATE_NEW = 'NewVote';
    const STATE_CANCELLED = 'VoteCancelled';
    const STATE_PASSED = 'VotePassed';
    const STATE_FAILED = 'VoteFailed';

    /** @var string */
    public $status;
    /** @var string */
    public $callerLogin;
    /** @var string */
    public $cmdName;
    /** @var mixed[] */
    public $cmdParam;

    /**
     * @param string $cmdName
     * @param mixed[] $cmdParam
     */
    function __construct($cmdName = '', $cmdParam = array())
    {
        $this->cmdName = $cmdName;
        $this->cmdParam = $cmdParam;
    }

    /**
     * @internal
     * @return bool
     */
    function isValid()
    {
        return is_string($this->cmdName)
            && is_array($this->cmdParam);
    }
}

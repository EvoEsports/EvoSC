<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class VoteRatio extends AbstractStructure
{
    const COMMAND_DEFAULT = '*';
    const COMMAND_SCRIPT_SETTINGS = 'SetModeScriptSettingsAndCommands';
    const COMMAND_NEXT_MAP = 'NextMap';
    const COMMAND_JUMP_MAP = 'JumpToMapIdent';
    const COMMAND_SET_NEXT_MAP = 'SetNextMapIdent';
    const COMMAND_RESTART_MAP = 'RestartMap';
    const COMMAND_TEAM_BALANCE = 'AutoTeamBalance';
    const COMMAND_KICK = 'Kick';
    const COMMAND_BAN = 'Ban';

    /** @var string '*' for default */
    public $command;
    /** @var string Empty to match all votes for the command */
    public $param;
    /** @var float Must be in range [0,1] or -1 to disable */
    public $ratio;

    /**
     * @param string $command
     * @param float $ratio
     */
    public function __construct($command = '', $ratio = 0.)
    {
        $this->command = $command;
        $this->ratio = $ratio;
        $this->param = '';
    }

    /**
     * @internal
     * @return bool
     */
    function isValid()
    {
        return is_string($this->command)
            && is_string($this->param)
            && self::isRatio($this->ratio);
    }

    /**
     * @internal
     * @param float $ratio
     * @return bool
     */
    static function isRatio($ratio)
    {
        return is_float($ratio) && ($ratio === -1. || ($ratio >= 0. && $ratio <= 1.));
    }
}

<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class Status extends AbstractStructure
{
    const UNKNOWN = 0;
    const WAITING = 1;
    const LAUNCHING = 2;
    const SYNCHRONIZATION = 3;
    const PLAY = 4;
    const EXITING = 6;
    const LOCAL = 7;

    /** @var int */
    public $code;
    /** @var string */
    public $name;
}

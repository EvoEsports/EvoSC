<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class ScriptSettings extends AbstractStructure
{
    /** @var string */
    public $name;
    /** @var string */
    public $desc;
    /** @var string */
    public $type;
    /** @var string */
    public $default;
}

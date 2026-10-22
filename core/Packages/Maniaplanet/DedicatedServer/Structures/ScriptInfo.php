<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class ScriptInfo extends AbstractStructure
{
    /** @var string */
    public $name;
    /** @var string */
    public $compatibleMapTypes;
    /** @var string */
    public $description;
    /** @var string */
    public $version;
    /** @var ScriptSettings[] */
    public $paramDescs = array();
    /** @var Command[] */
    public $commandDescs = array();

    /**
     * @return ScriptInfo
     */
    public static function fromArray($array)
    {
        $object = parent::fromArray($array);
        $object->paramDescs = ScriptSettings::fromArrayOfArray($object->paramDescs);
        $object->commandDescs = Command::fromArrayOfArray($object->commandDescs);
        return $object;
    }
}

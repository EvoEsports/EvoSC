<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class Skin extends AbstractStructure
{
    /** @var string */
    public $environnement;
    /** @var FileDesc */
    public $packDesc;

    /**
     * @return Skin
     */
    public static function fromArray($array)
    {
        $object = parent::fromArray($array);
        $object->packDesc = FileDesc::fromArray($object->packDesc);
        return $object;
    }
}

<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class Map extends AbstractStructure
{
    /** var string */
    public $uId;
    /** var string */
    public $name;
    /** var string */
    public $fileName;
    /** var string */
    public $author;
    /** var string */
    public $environnement;
    /** var string */
    public $mood;
    /** var int */
    public $bronzeTime;
    /** var int */
    public $silverTime;
    /** var int */
    public $goldTime;
    /** var int */
    public $authorTime;
    /** var int */
    public $copperPrice;
    /** var bool */
    public $lapRace;
    /** var int */
    public $nbLaps;
    /** var int */
    public $nbCheckpoints;
    /** var string */
    public $mapType;
    /** var string */
    public $mapStyle;

    /**
     * @return Map
     */
    public static function fromArray($array)
    {
        $object = parent::fromArray($array);
        $object->fileName = str_replace("\xEF\xBB\xBF", '', $object->fileName);
        return $object;
    }
}

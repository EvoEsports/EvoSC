<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class ForcedSkin extends AbstractStructure
{
    /** @var string */
    public $orig;
    /** @var string */
    public $name;
    /** @var string */
    public $checksum;
    /** @var string */
    public $url;
}

<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class Team extends AbstractStructure
{
    /** @var string */
    public $name;
    /** @var string */
    public $zonePath;
    /** @var string */
    public $city;
    /** @var string */
    public $emblemUrl;
    /** @var float */
    public $huePrimary;
    /** @var float */
    public $hueSecondary;
    /** @var string */
    public $rGB;
    /** @var string */
    public $clubLinkUrl;
}

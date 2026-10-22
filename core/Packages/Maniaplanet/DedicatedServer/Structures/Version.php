<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class Version extends AbstractStructure
{
    /** @var string */
    public $name;
    /** @var string */
    public $titleId;
    /** @var string */
    public $version;
    /** @var string */
    public $build;
    /** @var string */
    public $apiVersion;
}

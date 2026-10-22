<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class TokenInfos extends AbstractStructure
{
    /** @var int */
    public $tokenCost;
    /** @var bool */
    public $canPayToken;
}

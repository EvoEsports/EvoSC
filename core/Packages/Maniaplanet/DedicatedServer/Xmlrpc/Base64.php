<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Xmlrpc;

class Base64
{
    public $scalar;
    public $xmlrpc_type = 'base64';

    /**
     * @param string $data
     */
    function __construct($data)
    {
        $this->scalar = $data;
    }

    /**
     * @return string
     */
    function __toString()
    {
        return $this->scalar;
    }
}

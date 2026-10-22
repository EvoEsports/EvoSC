<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class SystemInfos extends AbstractStructure
{
    /** @var string */
    public $publishedIp;
    /** @var int */
    public $port;
    /** @var int */
    public $p2PPort;
    /** @var string */
    public $titleId;
    /** @var string */
    public $serverLogin;
    /** @var int */
    public $serverPlayerId;
    /** @var int */
    public $connectionDownloadRate;
    /** @var int */
    public $connectionUploadRate;
    /** @var bool */
    public $isServer;
    /** @var bool */
    public $isDedicated;
}

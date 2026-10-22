<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class LobbyInfo extends AbstractStructure
{
    /** var bool */
    public $isLobby;
    /** var int */
    public $lobbyPlayers;
    /** var int */
    public $lobbyMaxPlayers;
    /** var float */
    public $lobbyPlayersLevel;
}

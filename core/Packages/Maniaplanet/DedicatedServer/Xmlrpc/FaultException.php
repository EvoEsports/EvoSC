<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Xmlrpc;

class FaultException extends Exception
{
    static function create($faultString, $faultCode)
    {
        switch ($faultString) {
            case 'Password incorrect.':
            case 'Permission denied.':
                return new AuthenticationException($faultString, $faultCode);
            case 'not connected to the internet':
            case 'Not connected to the masterserver.':
            case 'Not a game server.':
            case 'Not a server.':
            case 'Couldn\'t create the fake player.':
            case 'Only server can receive a callvote':
            case 'No map currently loaded.':
            case 'No replay to save':
            case 'Internal error.':
                return new UnavailableFeatureException($faultString, $faultCode);
            case 'You must enable the callbacks to be able to do chat routing.':
            case 'Chat routing not enabled.':
            case 'Script cloud disabled.':
            case 'Already waiting for a vote.':
            case 'You must stop server first.':
                return new LockedFeatureException($faultString, $faultCode);
            case 'Can\'t kick server.':
            case 'Login or Uid unknown.':
            case 'Login unknown.':
            case 'Payer login unknown.':
                return new UnknownPlayerException($faultString, $faultCode);
            case 'The player is not a spectator':
            case 'The player is not a spectator.':
            case 'Not a network player.':
            case 'Player is not a fake player':
                return new PlayerStateException($faultString, $faultCode);
            case 'Player already ignored.':
            case 'Player already black listed.':
            case 'Player already on guest list.':
            case 'Map already added.':
                return new AlreadyInListException($faultString, $faultCode);
            case 'Login not banned.':
            case 'Player not ignored.':
            case 'Player not black listed.':
            case 'Player not on guest list.':
            case 'Map not in the selection.':
            case 'The map isn\'t in the current selection.':
            case 'Map not found.':
                return new NotInListException($faultString, $faultCode);
            case 'Start index out of bound.':
            case 'invalid index':
                return new IndexOutOfBoundException($faultString, $faultCode);
            case 'the next map must be different from the current one.':
            case 'No next map currently defined.':
                return new NextMapException($faultString, $faultCode);
            case 'Change in progress.':
                return new ChangeInProgressException($faultString, $faultCode);
            case 'Incompatible map type.':
            case 'Map not complete.':
            case 'Map corrupted.':
            case 'Map lightmap is not up to date.':
            case 'The map doesn\'t match the server packmask.':
                return new InvalidMapException($faultString, $faultCode);
            case 'Ladder mode unknown.':
            case 'You cannot change the max players count: AllowSpectatorRelays is activated.':
            case 'You cannot change the max spectators count: AllowSpectatorRelays is activated.':
            case 'There are too many players':
            case 'There are too many spectators':
            case 'Unknown hideserver value':
                return new ServerOptionsException($faultString, $faultCode);
            case 'New mode unknown.':
            case 'You need to stop the server to change to/from script mode.':
            case 'Not in script mode.':
            case 'Not in Team mode.':
            case 'Not in Rounds or Laps mode.':
            case 'The scores must be decreasing.':
            case 'No current script.':
                return new GameModeException($faultString, $faultCode);
            case 'Unable to write the black list file.':
            case 'Unable to write the guest list file.':
            case 'Unable to write the playlist file.':
            case 'Could not save file.':
            case 'Map unknown.':
            case 'The playlist file does not exist.':
            case 'Invalid url or file.':
            case 'Invalid url.':
                return new FileException($faultString, $faultCode);
        }
        if (preg_match('~^Unknown setting \'.*\'\.$~iu', $faultString)) {
            return new GameModeException($faultString, $faultCode);
        }
        if (preg_match('~^Couldn\'t load \'.*\'\.$~iu', $faultString)) {
            return new FileException($faultString, $faultCode);
        }

        return new self($faultString, $faultCode);
    }
}

class AuthenticationException extends FaultException
{
}

class UnavailableFeatureException extends FaultException
{
}

class LockedFeatureException extends FaultException
{
}

class UnknownPlayerException extends FaultException
{
}

class PlayerStateException extends FaultException
{
}

class AlreadyInListException extends FaultException
{
}

class NotInListException extends FaultException
{
}

class IndexOutOfBoundException extends FaultException
{
}

class NextMapException extends FaultException
{
}

class ChangeInProgressException extends FaultException
{
}

class InvalidMapException extends FaultException
{
}

class GameModeException extends FaultException
{
}

class ServerOptionsException extends FaultException
{
}

class FileException extends FaultException
{
}

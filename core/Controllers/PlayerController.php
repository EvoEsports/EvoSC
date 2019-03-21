<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Player;
use Maniaplanet\DedicatedServer\InvalidArgumentException;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

class PlayerController implements ControllerInterface
{
    public static function init()
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);

        AccessRight::createIfNonExistent('player_kick', 'Kick players.');
        AccessRight::createIfNonExistent('player_fake', 'Add/Remove fake player(s).');
        ChatCommand::add('//kick', [self::class, 'kickPlayer'], 'Kick player by nickname', 'player_kick');

        ManiaLinkEvent::add('kick', [self::class, 'kickPlayerEvent'], 'player_kick');
    }

    /**
     * Gets a player by name
     *
     * @param Player $callee
     * @param        $nick
     *
     * @return Player|null
     */
    public static function findPlayerByName(Player $callee, $nick): ?Player
    {
        $players = onlinePlayers()->filter(function (Player $player) use ($nick) {
            return str_contains(stripStyle(stripColors(strtolower($player))), strtolower($nick));
        });


        if ($players->count() == 0) {
            infoMessage('No player found.')->send($callee);

            return null;
        }

        if ($players->count() > 1) {
            infoMessage('Found more than one person, please be more specific.')->send($callee);

            return null;
        }

        return $players->first();
    }

    /**
     * Kick a player
     *
     * @param Player $player
     * @param        $cmd
     * @param        $nick
     * @param mixed  ...$message
     */
    public static function kickPlayer(Player $player, $cmd, $nick, ...$message)
    {
        $playerToBeKicked = self::findPlayerByName($player, $nick);

        if (!$playerToBeKicked) {
            return;
        }

        try {
            $reason = implode(" ", $message);
            Server::kick($playerToBeKicked->Login, $reason);
            warningMessage($player, ' kicked ', $playerToBeKicked, '. Reason: ', secondary($reason))->setIcon('')->sendAll();
        } catch (InvalidArgumentException $e) {
            Log::logAddLine('PlayerController', 'Failed to kick player: ' . $e->getMessage(), true);
            Log::logAddLine('PlayerController', '' . $e->getTraceAsString(), false);
        }
    }

    public static function kickPlayerEvent(Player $player, $login, $reason = "")
    {
        try {
            $toBeKicked = Player::find($login);
        } catch (\Exception $e) {
            $toBeKicked = $login;
        }

        try {
            $kicked = Server::rpc()->kick($login, $reason);
        } catch (Exception $e) {
            $kicked = Server::rpc()->disconnectFakePlayer($login);
        }

        if (!$kicked) {
            return;
        }

        if (strlen($reason) > 0) {
            warningMessage($player, ' kicked ', secondary($toBeKicked),
                secondary(' Reason: ' . $reason))->setIcon('')->sendAll();
        } else {
            warningMessage($player, ' kicked ', secondary($toBeKicked))->setIcon('')->sendAll();
        }
    }

    public static function playerConnect(Player $player)
    {
        global $_onlinePlayers;

        $diffString = $player->last_visit->diffForHumans();
        $stats      = $player->stats;

        if ($stats) {
            $message = infoMessage($player->group, ' ', $player, ' from ', secondary($player->path ?: '?'), ' joined, visits: ', secondary($stats->Visits), ' last visit ', secondary($diffString), '.')
                ->setIcon('');
        } else {
            $message = infoMessage($player->group, ' ', $player, ' from ', secondary($player->path ?: '?'), ' joined for the first time.')
                ->setIcon('');
        }

        if (config('server.echoes.leave')) {
            $message->sendAll();
        } else {
            $message->sendAdmin();
        }

        $player->update([
            'last_visit' => now(),
        ]);

        $_onlinePlayers->put($player->Login, $player);
    }

    public static function playerDisconnect(Player $player)
    {
        global $_onlinePlayers;

        $diff     = $player->last_visit->diffForHumans();
        $playtime = substr($diff, 0, -4);
        Log::info(stripAll($player) . " [" . $player->Login . "] left the server after $playtime.");
        $message = infoMessage($player, ' left the server after ', secondary($playtime), ' playtime.')->setIcon('');

        if (config('server.echoes.leave')) {
            $message->sendAll();
        } else {
            $message->sendAdmin();
        }

        $player->update([
            'last_visit' => now(),
        ]);

        $_onlinePlayers->forget($player->Login);
    }

    /**
     * Called on players finish
     *
     * @param Player $player
     * @param        $score
     */
    public static function playerFinish(Player $player, $score)
    {
        if ($player->isSpectator()) {
            //Leave spec when reset is pressed
            Server::forceSpectator($player->Login, 2);
            Server::forceSpectator($player->Login, 0);

            return;
        }

        if ($score > 0 && ($player->Score == 0 || $score < $player->Score)) {
            $player->setScore($score);
            Log::info($player . " finished with time ($score) " . $player->getTime());
        }
    }
}
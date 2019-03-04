<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Player;
use Maniaplanet\DedicatedServer\InvalidArgumentException;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

class PlayerController implements ControllerInterface
{
    private static $fakePlayers;

    public static function init()
    {
        Hook::add('PlayerConnect', [PlayerController::class, 'playerConnect']);
        Hook::add('PlayerDisconnect', [PlayerController::class, 'playerDisconnect']);
        Hook::add('PlayerFinish', [PlayerController::class, 'playerFinish']);

        AccessRight::createIfNonExistent('player_kick', 'Kick players.');
        AccessRight::createIfNonExistent('player_fake', 'Add/Remove fake player(s).');

        self::$fakePlayers = collect([]);
        ChatController::addCommand('kick', [PlayerController::class, 'kickPlayer'], 'Kick player by nickname', '//', 'player_kick');

        ManiaLinkEvent::add('kick', [self::class, 'kickPlayerEvent'], 'player_kick');

        ChatController::addCommand('fake', [PlayerController::class, 'connectFakePlayers'], 'Connect #n fake players', '##', 'player_fake');
        ChatController::addCommand('disfake', [PlayerController::class, 'disconnectFakePlayers'], 'Disconnect all fake players', '##', 'player_fake');
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

    /**
     * Connect N fake players
     *
     * @param Player $player
     * @param null   $cmd
     * @param null   $n
     */
    public static function connectFakePlayers(Player $player, $cmd = null, $n = null)
    {
        if (!$cmd || !$n) {
            return;
        }

        infoMessage('Adding ', intval($n), ' fake players');

        for ($i = 0; $i < intval($n); $i++) {
            $login = Server::connectFakePlayer();
            self::$fakePlayers->push($login);
        }
    }

    /**
     * Disconnect all fake players
     *
     * @param Player $player
     */
    public static function disconnectFakePlayers(Player $player)
    {
        self::$fakePlayers->each(function ($login) {
            Server::disconnectFakePlayer($login);
        });

        self::$fakePlayers = collect([]);
    }

    /**
     * Called on players connect
     *
     * @param Player $player
     * @param bool   $surpressJoinMessage
     *
     * @return Player
     */
    public static function playerConnect(Player $player): Player
    {
        $diffString = $player->last_visit->diffForHumans();

        $player->update([
            'last_visit' => now(),
            'player_id'  => PlayerController::getPlayerServerId($player),
        ]);

        chatMessage($player->group, ' ', $player, ' from ', secondary($player->path), ' joined, visits: ', secondary($player->stats->Visits), ' last visit ', secondary($diffString))
            ->setIcon('')
            ->sendAll();

        return $player;
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

    /**
     * Called on player disconnect
     *
     * @param Player|null $player
     * @param             $disconnectReason
     */
    public static function playerDisconnect(Player $player = null, $disconnectReason = '')
    {
        if ($player == null) {
            Log::info('SERVER SHUTTING DOWN');
            exit(0);
        }

        $diff = $player->last_visit->diffForHumans();
        Log::info(stripAll($player) . " [" . $player->Login . "] left the server after $diff.");

        infoMessage($player, ' left the server after ', secondary(str_replace(' ago', '', $diff)), ' playtime.')->sendAll();

        $player->update([
            'last_visit' => now(),
        ]);
    }

    public static function getPlayerByServerId(int $id): ?Player
    {
        try {
            return Player::wherePlayerId($id)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Hide liverankings
     */
    public static function hidePlayerlist()
    {
        Template::hideAll('players');
    }

    private static function getPlayerServerId(Player $player): int
    {
        return Server::rpc()->getPlayerInfo($player->Login)->playerId;
    }
}
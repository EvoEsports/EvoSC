<?php

namespace esc\Controllers;


use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Models\Player;
use esc\Models\Stats;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Maniaplanet\DedicatedServer\InvalidArgumentException;

class PlayerController
{
    private static $fakePlayers;

    public static function init()
    {
        Hook::add('PlayerDisconnect', [PlayerController::class, 'playerDisconnect']);
        Hook::add('PlayerFinish', [PlayerController::class, 'playerFinish']);

        self::$fakePlayers = collect([]);
        ChatController::addCommand('kick', [PlayerController::class, 'kickPlayer'], 'Kick player by nickname', '//', 'kick');
        ChatController::addCommand('ban', [PlayerController::class, 'banPlayer'], 'Ban player by nickname', '//', 'ban');

        ChatController::addCommand('fake', [PlayerController::class, 'connectFakePlayers'], 'Connect #n fake players', '##', 'ban');
        ChatController::addCommand('disfake', [PlayerController::class, 'disconnectFakePlayers'], 'Disconnect all fake players', '##', 'ban');
    }

    /**
     * Gets a player by name
     *
     * @param Player $callee
     * @param $nick
     * @return Player|null
     */
    public static function findPlayerByName(Player $callee, $nick): ?Player
    {
        $players = onlinePlayers()->filter(function (Player $player) use ($nick) {
            return str_contains(stripStyle(stripColors(strtolower($player->NickName))), strtolower($nick));
        });

        if ($players->count() == 0) {
            ChatController::message($callee, 'No player found');
            return null;
        }

        if ($players->count() > 1) {
            ChatController::message($callee, 'Found more than one person, please be more specific');
            return null;
        }

        return $players->first();
    }

    /**
     * Kick a player
     *
     * @param Player $player
     * @param $cmd
     * @param $nick
     * @param mixed ...$message
     */
    public static function kickPlayer(Player $player, $cmd, $nick, ...$message)
    {
        $playerToBeKicked = self::findPlayerByName($player, $nick);

        if (!$playerToBeKicked) return;

        try {
            $reason = implode(" ", $message);
            Server::kick($playerToBeKicked->Login, $reason);
            ChatController::messageAll($player->group->Name, ' ', $player, ' kicked ', $playerToBeKicked, '. Reason: ', secondary($reason));
        } catch (InvalidArgumentException $e) {
            Log::logAddLine('PlayerController', 'Failed to kick player: ' . $e->getMessage(), true);
            Log::logAddLine('PlayerController', '' . $e->getTraceAsString(), false);
        }
    }

    /**
     * Ban a player
     *
     * @param Player $player
     * @param $cmd
     * @param $nick
     * @param mixed ...$message
     */
    public static function banPlayer(Player $player, $cmd, $nick, ...$message)
    {
        $playerToBeBanned = self::findPlayerByName($player, $nick);

        if (!$playerToBeBanned) return;

        try {
            $reason = implode(" ", $message);
            Server::ban($playerToBeBanned->Login, $reason);
            Server::blackList($playerToBeBanned->Login);
            ChatController::messageAll($player->group->Name, ' ', $player, ' banned ', $playerToBeBanned, '. Reason: ', secondary($reason));
        } catch (InvalidArgumentException $e) {
            Log::logAddLine('PlayerController', 'Failed to ban player: ' . $e->getMessage(), true);
            Log::logAddLine('PlayerController', '' . $e->getTraceAsString(), false);
        }
    }

    /**
     * Connect N fake players
     *
     * @param Player $player
     * @param null $cmd
     * @param null $n
     */
    public static function connectFakePlayers(Player $player, $cmd = null, $n = null)
    {
        if (!$cmd || !$n) {
            return;
        }

        ChatController::messageAll('Adding ', intval($n), ' fake players');

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
     * @param bool $surpressJoinMessage
     * @return Player
     */
    public static function playerConnect(Player $player): Player
    {
        ChatController::messageAll('_info', $player->group->Name, ' ', $player, ' joined the server.');
        Log::info($player->NickName . " joined the server.");

        return $player;
    }

    /**
     * Called on players finish
     *
     * @param Player $player
     * @param $score
     */
    public static function playerFinish(Player $player, $score)
    {
        if ($player->isSpectator()) {
            Server::forceSpectator($player->Login, 2);
            Server::forceSpectator($player->Login, 0);
            return;
        }

        if ($score > 0 && ($player->Score == 0 || $score < $player->Score)) {
            $player->setScore($score);
            Log::info($player->NickName . " finished with time ($score) " . $player->getTime());
        }
    }

    /**
     * Called on player disconnect
     *
     * @param Player|null $player
     * @param $disconnectReason
     */
    public static function playerDisconnect(Player $player = null, $disconnectReason)
    {
        if ($player == null) {
            Log::info('SERVER SHUTTING DOWN');
            exit(0);
        }

        Log::info($player->NickName . " left the server [" . ($disconnectReason ?: 'disconnected') . "].");
        ChatController::messageAll('_info', $player, ' left the server');
    }

    public static function getPlayerByServerId(int $id): ?Player
    {
        try{
            return Player::wherePlayerId($id)->first();
        }catch(\Exception $e){
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
}
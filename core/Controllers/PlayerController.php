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
        Hook::add('PlayerDisconnect', 'PlayerController::playerDisconnect');
        Hook::add('PlayerFinish', 'PlayerController::playerFinish');

        self::$fakePlayers = collect([]);
        ChatController::addCommand('kick', 'PlayerController::kickPlayer', 'Kick player by nickname', '//', 'kick');
        ChatController::addCommand('ban', 'PlayerController::banPlayer', 'Ban player by nickname', '//', 'ban');

        ChatController::addCommand('fake', 'PlayerController::connectFakePlayers', 'Connect #n fake players', '##', 'ban');
        ChatController::addCommand('disfake', 'PlayerController::disconnectFakePlayers', 'Disconnect all fake players', '##', 'ban');
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
    public static function playerConnect(Player $player, bool $surpressJoinMessage = false): Player
    {
        $player->setOnline();

        $hooks = HookController::getHooks('PlayerConnect');
        HookController::fireHookBatch($hooks, $player);

        if (Database::hasTable('stats')) {
            $stats = $player->stats;

            if ($stats) {
                if (!$surpressJoinMessage) {
                    ChatController::messageAll('_info', $player->group->Name, ' ', $player, ' joined the server. Total visits ', $stats->Visits, ' last visited ', secondary($stats->updated_at->diffForHumans()));
                }
            }

            if (isset($stats->Rank) && $stats->Rank > 0) {
                $total = Stats::where('Rank', '>', 0)->count();
                ChatController::message($stats->player, '_info', 'Your server rank is ', secondary($stats->Rank . '/' . $total), ' (Score: ', $stats->Score, ')');
            }
        } else {
            if (!$surpressJoinMessage) {
                ChatController::messageAll('_info', $player->group->Name, ' ', $player, ' joined the server.');
            }
        }

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
        if ($score > 0 && ($player->Score == 0 || $score < $player->Score)) {
            $player->setScore($score);
            Log::info($player->NickName . " finished with time ($score) " . $player->getTime());
        }

        if ($player->isSpectator()) {
            Server::forceSpectator($player->Login, 2);
            Server::forceSpectator($player->Login, 0);
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
        $player->setOffline();
    }

    /**
     * Called on player info changed
     *
     * @param $infoplayerInfo
     */
    public static function playerInfoChanged($infoplayerInfo)
    {
        foreach ($infoplayerInfo as $info) {
            if (Player::where('Login', $info['Login'])->get()->isEmpty()) {
                $player = Player::create(['Login' => $info['Login'], 'Group' => 3]);
            } else {
                $player = Player::find($info['Login']);
            }

            if (!$player->stats && $player && $player->id) {
                Stats::create(['Player' => $player->id]);
            }

            $info['spectator_status'] = $info['SpectatorStatus'];
            $player->update($info);

            if (!$player->Online) {
                self::playerConnect($player);
            }
        }
    }

    public static function getPlayerByServerId(int $id): ?Player
    {
        try {
            $players = collect(Server::getRpc()->getPlayerList());
        } catch (\Exception $e) {
            Log::logAddLine('PlayerController', 'Failed to get player list', true);
            Log::logAddLine('PlayerController', $e->getTraceAsString());
            return null;
        }

        $playerInfo = $players->where('playerId', $id)->first();

        if ($playerInfo) {
            return Player::whereLogin($playerInfo->login)->first();
        }

        return null;
    }

    /**
     * Hide liverankings
     */
    public static function hidePlayerlist()
    {
        Template::hideAll('players');
    }
}
<?php

namespace esc\Controllers;


use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Models\Player;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Maniaplanet\DedicatedServer\InvalidArgumentException;
use Stats;

class PlayerController
{
    private static $lastManialinkHash;
    private static $fakePlayers;

    public static function init()
    {
        self::createTables();

        Hook::add('PlayerDisconnect', '\esc\Controllers\PlayerController::playerDisconnect');
        Hook::add('PlayerFinish', '\esc\Controllers\PlayerController::playerFinish');

        Template::add('players', File::get('core/Templates/players.latte.xml'));

        ChatController::addCommand('afk', '\esc\Controllers\PlayerController::toggleAfk', 'Toggle AFK status');
        ChatController::addCommand('hidespeed', '\esc\Controllers\PlayerController::setHideSpeed', 'Set speed at which UI hides, 0 = disable hiding');

        self::$fakePlayers = collect([]);
        ChatController::addCommand('kick', '\esc\Controllers\PlayerController::kickPlayer', 'Kick player by nickname', '//', 'kick');
        ChatController::addCommand('ban', '\esc\Controllers\PlayerController::banPlayer', 'Ban player by nickname', '//', 'ban');

        ChatController::addCommand('fake', '\esc\Controllers\PlayerController::connectFakePlayers', 'Connect #n fake players', '##', 'ban');
        ChatController::addCommand('disfake', '\esc\Controllers\PlayerController::disconnectFakePlayers', 'Disconnect all fake players', '##', 'ban');
    }

    public static function createTables()
    {
        Database::create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Login')->unique();
            $table->string('NickName')->default("unset");
            $table->integer('Group')->default(3);
            $table->integer('Score')->default(0);
            $table->boolean('Online')->default(false);
            $table->integer('Afk')->default(0);
            $table->integer('spectator_status')->default(0);
            $table->integer('MaxRank')->default(15);
            $table->boolean('Banned')->default(false);
            $table->text('user_settings')->nullable();
        });
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

    public static function setHideSpeed(Player $player, $cmd = null, $hideSpeed = 0)
    {
        if (!$cmd || $hideSpeed === null) {
            return;
        }

        $player->setSetting('ui->hideSpeed', $hideSpeed);

        if($hideSpeed == 0){
            ChatController::message($player, '_info', 'UI hiding disabled');
        }else{
            ChatController::message($player, '_info', 'UI hides now at ', $hideSpeed);
        }
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
     * Toggle AFK status (Deprecated)
     *
     * @param Player $player
     */
    public static function toggleAfk(Player $player)
    {
        $player->update(['Afk' => !$player->Afk]);
        self::displayPlayerlist();
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
        Log::info($player->NickName . " joined the server.");

        self::displayPlayerlist();

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
            self::displayPlayerlist();
        }

        if ($player->isSpectator()) {
            Server::forceSpectator($player->Login, 2);
            Server::forceSpectator($player->Login, 0);
        }

        $player->update(['Afk' => false]);
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
        $player->setScore(0);
        self::displayPlayerlist();
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

        self::displayPlayerlist();
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
            return Player::whereLogin($playerInfo->login)->get()->first();
        }

        return null;
    }

    /**
     * Show the live rnakings
     */
    public static function displayPlayerlist()
    {
        $players = onlinePlayers()
            ->where('Score', '>', 0)
            ->sort(function (Player $a, Player $b) {
                if ($a->Score < $b->Score) {
                    return -1;
                } else {
                    if ($a->Score > $b->Score) {
                        return 1;
                    }
                }

                return 0;
            });

        $playersNotFinished = onlinePlayers()
            ->where('Score', '=', 0)
            ->sort(function (Player $a, Player $b) {
                if ($a->Score < $b->Score) {
                    return -1;
                } else {
                    if ($a->Score > $b->Score) {
                        return 1;
                    }
                }

                return 0;
            });


        foreach ($playersNotFinished as $player) {
            $players->add($player);
        }

        Template::showAll('esc.box', [
            'id' => 'PlayerList',
            'title' => '  LIVE RANKINGS',
            'x' => config('ui.playerlist.x'),
            'y' => config('ui.playerlist.y'),
            'rows' => 13,
            'scale' => config('ui.playerlist.scale'),
            'content' => Template::toString('players', ['players' => $players->take(15)]),
        ]);
    }

    /**
     * Hide liverankings
     */
    public static function hidePlayerlist()
    {
        Template::hideAll('players');
    }
}
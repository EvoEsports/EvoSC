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
use esc\Models\Stats;
use Maniaplanet\DedicatedServer\InvalidArgumentException;
use Maniaplanet\DedicatedServer\Structures\PlayerInfo;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

/**
 * Class PlayerController
 *
 * @package esc\Controllers
 */
class PlayerController implements ControllerInterface
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $players;

    /**
     * Initialize PlayerController
     */
    public static function init()
    {
        //Add already connected players to the playerlist
        self::$players = collect(Server::getPlayerList(999, 0))->map(function (PlayerInfo $playerInfo) {
            $player = Player::firstOrCreate(['Login' => $playerInfo->login], [
                'NickName' => $playerInfo->nickName,
            ]);

            return $player;
        })->keyBy('Login');

        Hook::add('PlayerDisconnect', [self::class, 'playerDisconnect'], Hook::PRIORITY_LOWEST);
        Hook::add('PlayerConnect', [self::class, 'playerConnect'], Hook::PRIORITY_HIGHEST);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);

        AccessRight::createIfNonExistent('player_kick', 'Kick players.');
        AccessRight::createIfNonExistent('player_fake', 'Add/Remove fake player(s).');
        ChatCommand::add('//kick', [self::class, 'kickPlayer'], 'Kick player by nickname', 'player_kick');

        ManiaLinkEvent::add('kick', [self::class, 'kickPlayerEvent'], 'player_kick');
    }

    /**
     * Called on PlayerConnect
     *
     * @param \esc\Models\Player $player
     */
    public static function playerConnect(Player $player)
    {
        $diffString = $player->last_visit->diffForHumans();
        $stats      = $player->stats;

        if ($stats) {
            $message = infoMessage($player->group, ' ', $player, ' from ', secondary($player->path ?: '?'), ' joined, rank: ', secondary($stats->Rank), ' last visit ', secondary($diffString), '.')
                ->setIcon('');
        } else {
            $message = infoMessage($player->group, ' ', $player, ' from ', secondary($player->path ?: '?'), ' joined for the first time.')
                ->setIcon('');

            Stats::updateOrCreate(['Player' => $player->id], [
                'Visits' => 1,
            ]);
        }

        Log::logAddLine('PlayerController', $message->getMessage());

        if (config('server.echoes.join')) {
            $message->sendAll();
        } else {
            $message->sendAdmin();
        }

        $player->last_visit = now();
        $player->save();

        self::$players->put($player->Login, $player);

        var_dump(self::$players);
    }

    /**
     * Called on PlayerDisconnect
     *
     * @param \esc\Models\Player $player
     */
    public static function playerDisconnect(Player $player)
    {
        $diff     = $player->last_visit->diffForHumans();
        $playtime = substr($diff, 0, -4);
        Log::logAddLine('PlayerController', $player . " [" . $player->Login . "] left the server after $playtime playtime.");
        $message = infoMessage($player, ' left the server after ', secondary($playtime), ' playtime.')->setIcon('');

        if (config('server.echoes.leave')) {
            $message->sendAll();
        } else {
            $message->sendAdmin();
        }

        $player->update([
            'last_visit' => now(),
        ]);

        self::$players = self::$players->forget($player->Login);
    }

    /**
     * Gets a player by nickname or login.
     *
     * @param Player $callee
     * @param        $nick
     *
     * @return Player|null
     */
    public static function findPlayerByName(Player $callee, $nick): ?Player
    {
        $players = onlinePlayers()->filter(function (Player $player) use ($nick) {
            return strpos(stripStyle(stripColors(strtolower($player))), strtolower($nick)) !== false || $player->Login == $nick;
        });


        if ($players->count() == 0) {
            warningMessage('No player found.')->send($callee);

            return null;
        }

        if ($players->count() > 1) {
            warningMessage('Found more than one person (' . $players->pluck('NickName')->implode(', ') . '), please be more specific or use login.')->send($callee);

            return null;
        }

        return $players->first();
    }

    /**
     * Kick a player.
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

    /**
     * ManiaLinkEvent: kick player
     *
     * @param \esc\Models\Player $player
     * @param                    $login
     * @param string             $reason
     *
     * @throws \Maniaplanet\DedicatedServer\InvalidArgumentException
     */
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

        if ($score > 0) {
            $player->Score = $score;
            $player->save();
            Log::info($player . " finished with time ($score) " . $player->getTime());
        }
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public static function getPlayers(): \Illuminate\Support\Collection
    {
        return self::$players;
    }

    public static function hasPlayer(string $login)
    {
        return self::$players->has($login);
    }

    public static function getPlayer(string $login): Player
    {
        return self::$players->get($login);
    }

    public static function addPlayer(Player $player)
    {
        return self::$players->put($player->Login, $player);
    }
}
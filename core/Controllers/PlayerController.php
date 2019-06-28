<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Map;
use esc\Models\Player;
use esc\Models\Stats;
use Illuminate\Support\Collection;
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
     * @var Collection
     */
    private static $players;

    /** @var int */
    private static $stringEditDistanceThreshold = 8;

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

            $player->spectator_status = $playerInfo->spectatorStatus;
            $player->player_id = $playerInfo->playerId;

            return $player;
        })->keyBy('Login');

        Hook::add('PlayerDisconnect', [self::class, 'playerDisconnect']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);

        AccessRight::createIfNonExistent('player_kick', 'Kick players.');
        AccessRight::createIfNonExistent('player_fake', 'Add/Remove fake player(s).');
        AccessRight::createIfNonExistent('override_join_msg', 'Always announce join/leave.');

        ManiaLinkEvent::add('kick', [self::class, 'kickPlayerEvent'], 'player_kick');

        ChatCommand::add('//setpw', [self::class, 'setServerPassword'],
            'Set the server password, leave empty to clear it.', 'ma');
        ChatCommand::add('//kick', [self::class, 'kickPlayer'], 'Kick player by nickname', 'player_kick');
    }

    /**
     * @param  Player  $player
     * @param  string  $cmd
     * @param  string  $pw
     */
    public static function setServerPassword(Player $player, $cmd, $pw)
    {
        if (Server::setServerPassword($pw)) {
            if ($pw == '') {
                infoMessage($player, ' cleared the server password.')->sendAll();
            } else {
                infoMessage($player, ' set a server password.')->sendAll();
                infoMessage($player, ' set a server password to "'.$pw.'".')->sendAdmin();
            }
        }
    }

    /**
     * Called on PlayerConnect
     *
     * @param  Player  $player
     *
     * @throws \Exception
     */
    public static function playerConnect(Player $player)
    {
        $diffString = $player->last_visit->diffForHumans();
        $stats = $player->stats;

        if ($stats) {
            $message = infoMessage($player->group, ' ', $player, ' from ', secondary($player->path ?: '?'),
                ' joined, rank: ', secondary($stats->Rank), ' last visit ', secondary($diffString), '.')
                ->setIcon('');
        } else {
            $message = infoMessage($player->group, ' ', $player, ' from ', secondary($player->path ?: '?'),
                ' joined for the first time.')
                ->setIcon('');

            Stats::updateOrCreate(['Player' => $player->id], [
                'Visits' => 1,
            ]);
        }

        Log::logAddLine('PlayerController', $message->getMessage());

        if (config('server.echoes.join') || $player->hasAccess('override_join_msg')) {
            $message->sendAll();
        } else {
            $message->sendAdmin();
        }

        $player->last_visit = now();
        $player->save();

        self::$players->put($player->Login, $player);
    }

    /**
     * Called on PlayerDisconnect
     *
     * @param  Player  $player
     *
     * @throws \Exception
     */
    public static function playerDisconnect(Player $player)
    {
        $diff = $player->last_visit->diffForHumans();
        $playtime = substr($diff, 0, -4);
        Log::logAddLine('PlayerController',
            $player." [".$player->Login."] left the server after $playtime playtime.");
        $message = infoMessage($player, ' left the server after ', secondary($playtime), ' playtime.')->setIcon('');

        if (config('server.echoes.leave')) {
            $message->sendAll();
        } else {
            $message->sendAdmin();
        }

        $player->update([
            'last_visit' => now(),
            'player_id'  => 0,
        ]);

        self::$players = self::$players->forget($player->Login);
    }

    /**
     * Reset player ids on begin map
     *
     * @param  Map  $map
     */
    public static function beginMap(Map $map)
    {
        Player::where('player_id', '>', 0)->orWhere('spectator_status', '>', 0)->update([
            'player_id'        => 0,
            'spectator_status' => 0,
        ]);
    }

    /**
     * Gets a player by nickname or login.
     *
     * @param  Player  $callee
     * @param  string  $nick
     *
     * @return Player|null
     */
    public static function findPlayerByName(Player $callee, $nick): ?Player
    {
        $online = onlinePlayers();
        $nicknamesByLogin = [];

        foreach ($online->all() as $player) {
            $nicknamesByLogin[$player->Login] = stripAll($player->NickName);
        };

        $fuzzyLogin = self::findClosestMatchingString($nick, $nicknamesByLogin);

        $players = $online->filter(function (Player $player) use ($nick, $fuzzyLogin) {
            if ($player->Login == $nick || ($fuzzyLogin !== null && $player->Login == $fuzzyLogin)) {
                return true;
            }

            return false;
        });


        if ($players->count() == 0) {
            warningMessage('No player found.')->send($callee);

            return null;
        }

        if ($players->count() > 1) {
            warningMessage('Found more than one person ('.$players->pluck('NickName')->implode(', ').'), please be more specific or use login.')->send($callee);

            return null;
        }

        return $players->first();
    }

    /**
     * Kick a player.
     *
     * @param  Player  $player
     * @param        $cmd
     * @param        $nick
     * @param  mixed  ...$message
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
            warningMessage($player, ' kicked ', $playerToBeKicked, '. Reason: ',
                secondary($reason))->setIcon('')->sendAll();
        } catch (InvalidArgumentException $e) {
            Log::logAddLine('PlayerController', 'Failed to kick player: '.$e->getMessage(), true);
            Log::logAddLine('PlayerController', ''.$e->getTraceAsString(), false);
        }
    }

    /**
     * ManiaLinkEvent: kick player
     *
     * @param  Player  $player
     * @param  string  $login
     * @param  string  $reason
     */
    public static function kickPlayerEvent(Player $player, $login, $reason = "")
    {
        try {
            $toBeKicked = Player::find($login);
        } catch (\Exception $e) {
            $toBeKicked = $login;
        }

        try {
            $kicked = Server::kick($login, $reason);
        } catch (Exception $e) {
            $kicked = Server::disconnectFakePlayer($login);
        }

        if (!$kicked) {
            return;
        }

        if (strlen($reason) > 0) {
            warningMessage($player, ' kicked ', secondary($toBeKicked),
                secondary(' Reason: '.$reason))->setIcon('')->sendAll();
        } else {
            warningMessage($player, ' kicked ', secondary($toBeKicked))->setIcon('')->sendAll();
        }
    }

    /**
     * Called on players finish
     *
     * @param  Player  $player
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
            Log::info($player." finished with time ($score) ".$player->getTime());
        }
    }

    /**
     * @return Collection
     */
    public static function getPlayers(): Collection
    {
        return self::$players;
    }

    /**
     * @param  string  $login
     *
     * @return bool
     */
    public static function hasPlayer(string $login)
    {
        return self::$players->has($login);
    }

    /**
     * @param  string  $login
     *
     * @return Player
     */
    public static function getPlayer(string $login): Player
    {
        return self::$players->get($login);
    }

    /**
     * @param  Player  $player
     *
     * @return Collection
     */
    public static function addPlayer(Player $player)
    {
        return self::$players->put($player->Login, $player);
    }


    private static function findClosestMatchingString(string $search, array $array)
    {
        $closestDistanceThusFar = self::$stringEditDistanceThreshold + 1;
        $closestMatchValue = null;

        foreach ($array as $key => $value) {
            $editDistance = levenshtein($value, $search);

            if ($editDistance == 0) {
                return $key;

            } elseif ($editDistance <= $closestDistanceThusFar) {
                $closestDistanceThusFar = $editDistance;
                $closestMatchValue = $key;
            }
        }

        return $closestMatchValue; // possible to return null if threshold hasn't been met
    }

}
<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use Exception;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Structures\PlayerInfo;

/**
 * Class PlayerController
 *
 * @package EvoSC\Controllers
 */
class PlayerController implements ControllerInterface
{
    /**
     * @var Collection
     */
    private static Collection $players;

    /** @var int */
    private static int $stringEditDistanceThreshold = 8;

    /**
     * Initialize PlayerController
     */
    public static function init()
    {
        //Add already connected players to the player-list
        self::cacheConnectedPlayers();

        AccessRight::add('player_kick', 'Kick players.');
        AccessRight::add('player_warn', 'Warn a player.');
        AccessRight::add('player_force_spec', 'Force a player into spectator mode.');
        AccessRight::add('always_print_join_msg', 'Always announce join/leave.');
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        Hook::add('PlayerDisconnect', [self::class, 'playerDisconnect']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);

        ManiaLinkEvent::add('kick', [self::class, 'kickPlayerEvent'], 'player_kick');
        ManiaLinkEvent::add('forcespec', [self::class, 'forceSpecEvent'], 'player_force_spec');
        ManiaLinkEvent::add('spec', [self::class, 'specPlayer']);
        ManiaLinkEvent::add('mute', [PlayerController::class, 'muteLoginToggle'], 'player_mute');

        ChatCommand::add('//setpw', [self::class, 'cmdSetServerPassword'],
            'Set the server password, leave empty to clear it.', 'ma')->addAlias('//password');
        ChatCommand::add('//kick', [self::class, 'kickPlayer'], 'Kick player by nickname', 'player_kick');
        ChatCommand::add('//fakeplayer', [self::class, 'addFakePlayer'], 'Adds N fakeplayers.', 'ma');
        ChatCommand::add('/reset-ui', [self::class, 'resetUserSettings'], 'Resets all user-settings to default.');
        ChatCommand::add('/setname', [self::class, 'setName'], 'Change NickName.');
    }

    public static function setName(Player $player, $cmd, ...$name)
    {
        $name = trim(implode(' ', $name));
        if(strlen($name) == 0){
            warningMessage('Your name can not be empty.')->send($player);
            return;
        }
        infoMessage($player, ' changed their name to ', secondary($name))->sendAll();
        infoMessage('This is temporarily and will reset once you rejoin.')->send($player);
        $player->NickName = $name;
        $player->update([
            'NickName' => $name
        ]);
        self::$players->put($player->Login, $player);
    }

    public static function cacheConnectedPlayers()
    {
        self::$players = collect(Server::getPlayerList(999, 0))->map(function (PlayerInfo $playerInfo) {
            return Player::updateOrCreate(['Login' => $playerInfo->login], [
                'NickName' => $playerInfo->nickName,
                'spectator_status' => $playerInfo->spectatorStatus,
                'player_id' => $playerInfo->playerId
            ]);
        })->keyBy('Login');
    }

    /**
     * @param Player $player
     * @param mixed ...$pw
     */
    public static function cmdSetServerPassword(Player $player, $cmd, ...$pw)
    {
        $pw = trim(implode(' ', $pw));

        if (Server::setServerPassword($pw)) {
            if ($pw == '') {
                infoMessage($player, ' cleared the server password.')->sendAll();
            } else {
                infoMessage($player, ' set a server password.')->sendAll();
                infoMessage($player, ' set the server password to ', secondary($pw))->sendAdmin();
            }
        }

        Server::setServerPasswordForSpectator($pw);
    }

    /**
     * Called on PlayerConnect
     *
     * @param Player $player
     *
     * @throws Exception
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

            DB::table('stats')->insertOrIgnore([
                'Player' => $player->id,
                'Visits' => 1,
            ]);
        }

        Log::write($message->getMessage());

        if (config('server.echoes.join') || $player->hasAccess('always_print_join_msg')) {
            $message->sendAll();
        } else {
            $message->sendAdmin();
        }

        $player->last_visit = now();
        $player->save();

        self::$players->put($player->Login, $player);

        //TODO: Remove when nadeo allows setting nicknames in TMN
        if (isTrackmania()) {
            warningMessage('Use ', secondary('/setname <name>'), ' to temporarily set a name on this server.')->send($player);
        }
    }

    /**
     * Called on PlayerDisconnect
     *
     * @param Player $player
     *
     * @throws \Exception
     */
    public static function playerDisconnect(Player $player)
    {
        $diff = $player->last_visit->diffForHumans();
        $playtime = substr($diff, 0, -4);
        Log::write($player . " [" . $player->Login . "] left the server after $playtime playtime.");
        $message = infoMessage($player, ' left the server after ', secondary($playtime), ' playtime.')->setIcon('');

        if (config('server.echoes.leave')) {
            $message->sendAll();
        } else {
            $message->sendAdmin();
        }

        $player->update([
            'last_visit' => now(),
            'player_id' => 0,
        ]);

        self::$players = self::$players->forget($player->Login);
    }

    /**
     * Reset player ids on begin map
     *
     */
    public static function beginMap()
    {
        DB::table('players')
            ->where('player_id', '>', 0)
            ->orWhere('spectator_status', '>', 0)
            ->update([
                'player_id' => 0,
                'spectator_status' => 0,
            ]);

        DB::table('players')
            ->where('Score', '>', 0)
            ->update([
                'Score' => 0,
            ]);
    }

    /**
     * Gets a player by nickname or login.
     *
     * @param Player $callee
     * @param string $nick
     *
     * @return Player|null
     */
    public static function findPlayerByName(Player $callee, $nick): ?Player
    {
        $online = onlinePlayers();
        $nicknamesByLogin = [];

        foreach ($online->all() as $player) {
            $nicknamesByLogin[$player->Login] = stripAll($player->NickName);
        }

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
            warningMessage('Found more than one person (' . $players->pluck('NickName')->implode(', ') . '), please be more specific or use login.')->send($callee);

            return null;
        }

        return $players->first();
    }

    /**
     * Kick a player.
     *
     * @param Player $player
     * @param        $nick
     * @param mixed ...$message
     */
    public static function kickPlayer(Player $player, $nick, ...$message)
    {
        $playerToBeKicked = self::findPlayerByName($player, $nick);

        if (!$playerToBeKicked) {
            return;
        }

        if ($playerToBeKicked->Group < $player->Group) {
            warningMessage('You can not kick players with a higher group-rank than yours.')->send($player);
            infoMessage($player, ' tried to kick you but was blocked.')->send($playerToBeKicked);
            return;
        }

        $reason = implode(" ", $message);
        Server::kick($playerToBeKicked->Login, $reason);
        warningMessage($player, ' kicked ', $playerToBeKicked, '. Reason: ',
            secondary($reason))->setIcon('')->sendAll();
    }

    /**
     * ManiaLinkEvent: kick player
     *
     * @param Player $player
     * @param string $login
     * @param string $reason
     */
    public static function kickPlayerEvent(Player $player, $login, $reason = "")
    {
        try {
            $toBeKicked = Player::find($login);
        } catch (\Exception $e) {
            $toBeKicked = $login;
        }

        if ($toBeKicked->Group < $player->Group) {
            warningMessage('You can not kick players with a higher group-rank than yours.')->send($player);
            infoMessage($player, ' tried to kick you but was blocked.')->send($toBeKicked);
            return;
        }

        $kicked = Server::kick($login, $reason);

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
     * @param int $score
     * @param string $checkpoints
     */
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($player->isSpectator()) {
            //Leave spec when reset is pressed
            Server::forceSpectator($player->Login, 2);
            Server::forceSpectator($player->Login, 0);

            return;
        }

        if ($score > 0) {
            Log::info($player . "\$z finished with time ($score) " . formatScore($score));

            $player->Score = $score;
            $player->save();

            $map = MapController::getCurrentMap();

            $hasBetterTime = DB::table('pbs')
                ->where('map_id', '=', $map->id)
                ->where('player_id', '=', $player->id)
                ->where('score', '<=', $score)
                ->exists();

            if (!$hasBetterTime) {
                DB::table('pbs')->updateOrInsert([
                    'map_id' => $map->id,
                    'player_id' => $player->id
                ], [
                    'score' => $score,
                    'checkpoints' => $checkpoints
                ]);

                Hook::fire('PlayerPb', $player, $score, $checkpoints);
            }
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
     * @param string $login
     *
     * @return bool
     */
    public static function hasPlayer(string $login)
    {
        return self::$players->has($login);
    }

    /**
     * @param string $login
     *
     * @return Player
     */
    public static function getPlayer(string $login): Player
    {
        return self::$players->get($login);
    }

    /**
     * @param Player $player
     *
     * @return Collection
     */
    public static function putPlayer(Player $player)
    {
        return self::$players->put($player->Login, $player);
    }

    public static function forceSpecEvent(Player $player, string $targetLogin)
    {
        Server::forceSpectator($targetLogin, 3);

        infoMessage($player, ' forced ', player($targetLogin), ' into spectator mode.')->sendAll();
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

    public static function addFakePlayer(Player $player, string $cmd, string $count = '1')
    {
        infoMessage($player, ' adds ', secondary($count), ' fake players.')->sendAll();

        for ($i = 0; $i < intval($count); $i++) {
            Server::connectFakePlayer();
        }
    }

    public static function resetUserSettings(Player $player)
    {
        $player->settings()->delete();
        infoMessage('Your settings have been cleared. You may want to call ', secondary('/reset'))->send($player);
    }

    public static function specPlayer(Player $player, $targetLogin)
    {
        Server::forceSpectator($player->Login, 3);
        Server::forceSpectatorTarget($player->Login, $targetLogin, 1);
    }

    public static function muteLoginToggle(Player $player, $targetLogin)
    {
        $target = player($targetLogin);

        if (ChatController::isPlayerMuted($target)) {
            ChatController::unmute($player, $target);
        } else {
            ChatController::mute($player, $target);
        }
    }
}
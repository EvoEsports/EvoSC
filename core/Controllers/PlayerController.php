<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\AwaitAction;
use EvoSC\Classes\Cache;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
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

    private static Collection $customNames;

    /**
     * Initialize PlayerController
     */
    public static function init()
    {
        self::$customNames = collect();
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
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);

        ManiaLinkEvent::add('kick', [self::class, 'kickPlayerEvent'], 'player_kick');
        ManiaLinkEvent::add('forcespec', [self::class, 'forceSpecEvent'], 'player_force_spec');
        ManiaLinkEvent::add('spec', [self::class, 'specPlayer']);
        ManiaLinkEvent::add('mute', [PlayerController::class, 'muteLoginToggle'], 'player_mute');

        InputSetup::add('leave_spec', 'Leave spectator mode.', [self::class, 'leaveSpec'], 'Delete');

        ChatCommand::add('//setpw', [self::class, 'cmdSetServerPassword'],
            'Set the server password, leave empty to clear it.', 'ma')->addAlias('//password');
        ChatCommand::add('//kick', [self::class, 'kickPlayer'], 'Kick player by nickname', 'player_kick');
        ChatCommand::add('//fakeplayer', [self::class, 'addFakePlayer'], 'Adds N fakeplayers.', 'ma');
        ChatCommand::add('/reset-ui', [self::class, 'resetUserSettings'], 'Resets all user-settings to default.');

        if (isTrackmania()) {
            ChatCommand::add('/setname', [self::class, 'setName'], 'Change NickName.');
        }
    }

    /**
     * @param Player $player
     */
    public static function leaveSpec(Player $player){
        if($player->isSpectator()){
            Server::forceSpectator($player->Login, 2);
            Server::forceSpectator($player->Login, 0);
        }
    }

    public static function setName(Player $player, $cmd, ...$name)
    {
        $oldName = $player->NickName;
        $name = str_replace("\n", '', trim(implode(' ', $name)));
        if (strlen(trim(stripAll($name))) == 0) {
            warningMessage('Your name can not be empty.')->send($player);
            return;
        }
        if (strlen(stripAll($name)) > 28) {
            warningMessage('Your name can not exceed 29 characters.')->send($player);
            return;
        }
        $player->NickName = $name;
        $player->update([
            'NickName' => $name
        ]);
        self::$customNames->put($player->Login, $name);
        self::$players->put($player->Login, $player);
        if ($cmd != 'silent') {
            infoMessage(secondary($oldName), ' changed their name to ', secondary($name))->sendAll();
            Cache::put('nicknames/' . $player->Login, $name);
        }
        self::sendUpdatesCustomNames();
        Hook::fire('PlayerChangedName', $player);
    }

    private static function sendUpdatesCustomNames()
    {
        Template::showAll('Helpers.update-custom-names', ['names' => self::$customNames]);
    }

    public static function cacheConnectedPlayers()
    {
        self::$players = collect(Server::getPlayerList(999, 0))->map(function (PlayerInfo $playerInfo) {
            $name = $playerInfo->nickName;

            if (isTrackmania() && Cache::has('nicknames/' . $playerInfo->login)) {
                $name = Cache::get('nicknames/' . $playerInfo->login);
                self::$customNames->put($playerInfo->login, $name);
            }

            return Player::updateOrCreate(['Login' => $playerInfo->login], [
                'NickName' => $name,
                'spectator_status' => $playerInfo->spectatorStatus,
                'player_id' => $playerInfo->playerId
            ]);
        })->keyBy('Login');

        self::sendUpdatesCustomNames();
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
            $serverRank = $stats->Rank;

            if($serverRank == -1){
                $serverRank = 'unranked';
            }

            $message = infoMessage($player->group, ' ', $player, ' from ', secondary($player->path ?: '?'),
                ' joined, rank: ', secondary($serverRank), ' last visit ', secondary($diffString), '.')
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

        if (self::$customNames->has($player->Login)) {
            self::$customNames->forget($player->Login);
        }
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
        $found = collect();

        foreach (onlinePlayers() as $player) {
            $stripped = strtolower(stripAll($player->NickName));
            if (strpos($stripped, strtolower($nick)) !== false || $nick == $player->Login) {
                $found->add($player);
            }
        }

        if ($found->count() == 0) {
            warningMessage('No player found.')->send($callee);

            return null;
        }

        if ($found->count() > 1) {
            warningMessage('Found more than one person (' . $found->pluck('NickName')->implode(', ') . '), please be more specific or use login.')->send($callee);

            return null;
        }

        return $found->first();
    }

    /**
     * Kick a player.
     *
     * @param Player $player
     * @param        $nick
     * @param mixed ...$reason
     */
    public static function kickPlayer(Player $player, $cmd, $nick, ...$reason)
    {
        $playerToBeKicked = self::findPlayerByName($player, $nick);

        if ($playerToBeKicked == null) {
            return;
        }

        if ($playerToBeKicked->Group < $player->Group) {
            warningMessage('You can not kick players with a higher group-rank than yours.')->send($player);
            infoMessage($player, ' tried to kick you but was blocked.')->send($playerToBeKicked);
            return;
        }

        $reason = implode(' ', $reason);

        AwaitAction::add($player, "Kick \$<$playerToBeKicked->NickName\$>?", function () use ($playerToBeKicked, $reason, $player) {
            Server::kick($playerToBeKicked->Login, $reason);
            warningMessage($player, ' kicked ', $playerToBeKicked->NickName, '. Reason: ',
                secondary($reason))->setIcon('')->sendAll();
        });
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
            Log::info(stripAll($player) . " finished with time ($score) " . formatScore($score));

            $player->Score = $score;
            $player->save();

            $map = MapController::getCurrentMap();

            $hasBetterTime = DB::table('pbs')
                ->where('map_id', '=', $map->id)
                ->where('player_id', '=', $player->id)
                ->where('score', '<', $score)
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
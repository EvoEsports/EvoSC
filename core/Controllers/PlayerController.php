<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\Cache;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Group;
use EvoSC\Models\Player;
use EvoSC\Models\SetnameBlacklist;
use EvoSC\Modules\InputSetup\InputSetup;
use Exception;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    private static bool $loadNicknamesFromEvoService = false;

    /**
     * Initialize PlayerController
     */
    public static function init()
    {
        self::$loadNicknamesFromEvoService = (bool)config('server.load-nicknames-from-evo-service', false);
        //Add already connected players to the player-list
        self::cacheConnectedPlayers();

        AccessRight::add('player_kick', 'Kick players.');
        AccessRight::add('player_warn', 'Warn a player.');
        AccessRight::add('player_force_spec', 'Force a player into spectator mode.');
        AccessRight::add('always_print_join_msg', 'Always announce join/leave.');
        AccessRight::add('player_reset_name', 'Reset ones nickname to the ubi-name.');
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
        Hook::add('PlayerConnect', [self::class, 'playerConnect'], false, 10);
        Hook::add('GroupChanged', [self::class, 'playerGroupChanged'], false, 10);

        ManiaLinkEvent::add('kick', [self::class, 'kickPlayerEvent'], 'player_kick');
        ManiaLinkEvent::add('forcespec', [self::class, 'forceSpecEvent'], 'player_force_spec');
        ManiaLinkEvent::add('spec', [self::class, 'specPlayer']);
        ManiaLinkEvent::add('mute', [PlayerController::class, 'muteLoginToggle'], 'player_mute');
        ManiaLinkEvent::add('warn', [self::class, 'warnPlayer'], 'player_warn');
        ManiaLinkEvent::add('reset_nickname', [self::class, 'resetName'], 'player_reset_name');
        ManiaLinkEvent::add('allow_setname', [self::class, 'allowSetname'], 'player_reset_name');
        ManiaLinkEvent::add('reset_nickname_and_block', [self::class, 'resetNameAndBlacklist'], 'player_reset_name');

        InputSetup::add('leave_spec', 'Leave spectator mode.', [self::class, 'leaveSpec'], 'Delete');

        ChatCommand::add('//setpw', [self::class, 'cmdSetServerPassword'],
            'Set the server password, leave empty to clear it.', 'ma')->addAlias('//password');
        ChatCommand::add('//kick', [self::class, 'kickPlayer'], 'Kick player by nickname', 'player_kick');
        ChatCommand::add('//fakeplayer', [self::class, 'addFakePlayer'], 'Adds N fakeplayers.', 'ma');
        ChatCommand::add('/reset-ui', [self::class, 'resetUserSettings'], 'Resets all user-settings to default.');
    }

    /**
     * @param Player $player
     */
    public static function leaveSpec(Player $player)
    {
        if ($player->isSpectator()) {
            Server::forceSpectator($player->Login, 2);
            Server::forceSpectator($player->Login, 0);
        }
    }

    /**
     * @param Player $player
     * @param $name
     * @param false $silent
     * @param false $fromCache
     */
    public static function setName(Player $player, $name, $silent = false, $fromCache = false)
    {
        if ($name == $player->NickName) {
            return;
        }
        if (strlen(trim(stripAll($name))) == 0) {
            warningMessage('Your name can not be empty.')->send($player);
            return;
        }
        if (strlen(stripAll($name)) > 38) {
            warningMessage('Your name can not exceed 39 characters.')->send($player);
            return;
        }
        $oldName = $player->NickName;
        $player->NickName = $name;
        $player->save();
        self::$players->put($player->Login, $player);
        if (!$silent && !ChatController::isPlayerMuted($player)) {
            infoMessage(secondary($oldName), ' changed their name to ', secondary($name))->sendAll();
        }
        Cache::put('nicknames/' . $player->Login, $name);
        self::playerPoolChanged();

        if (!$fromCache) {
            Hook::fire('PlayerChangedName', $player);
        }
    }

    /**
     * @param null $value
     */
    public static function playerPoolChanged($value = null)
    {
        if (isManiaPlanet()) {
            return;
        }

        $data = self::$players->map(function (Player $player) {
            if ($player->NickName == $player->ubisoft_name) {
                return null;
            }

            return [
                'login'   => $player->Login,
                'name'    => $player->NickName,
                'ubiname' => $player->ubisoft_name,
            ];
        })->filter();

        Template::showAll('Helpers.update-custom-names', [
            'keyedByLogin'   => $data->pluck('name', 'login'),
            'keyedByUbiname' => $data->pluck('name', 'ubiname')
        ]);
    }

    /**
     * @param Player $player
     * @return void
     */
    public static function playerGroupChanged(Player $player)
    {
        self::$players->put($player->Login, $player);
    }

    /**
     * @return void
     */
    public static function cacheConnectedPlayers()
    {
        self::$players = \Illuminate\Database\Eloquent\Collection::make(Server::getPlayerList())
            ->map(function (PlayerInfo $playerInfo) {
                try {
                    return Player::where('Login', '=', $playerInfo->login)
                        ->firstOrFail();
                } catch (ModelNotFoundException $e) {
                    return Player::create([
                        'Login'            => $playerInfo->login,
                        'NickName'         => $playerInfo->nickName,
                        'ubisoft_name'     => $playerInfo->nickName,
                        'spectator_status' => $playerInfo->spectatorStatus,
                        'player_id'        => $playerInfo->playerId,
                        'team'             => $playerInfo->teamId,
                        'group_id'         => Group::PLAYER
                    ]);
                }
            })
            ->load('group')
            ->keyBy('Login');

        self::playerPoolChanged();
    }

    /**
     * @param Player $player
     * @param $cmd
     * @param ...$pw
     * @return void
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
        if (isManiaPlanet() || !self::$loadNicknamesFromEvoService) {
            self::announceConnect($player);
            return;
        }

        if (preg_match('/\*fakeplayer\d+\*/', $player->Login)) {
            Log::info('Skip load nickname from cloud for fakeplayer: ' . $player->Login, isVerbose());
            self::announceConnect($player);
            return;
        }

        RestClient::getAsync(sprintf(EVO_API_URL . '/nicknames/%s', $player->Login), [
            'connect_timeout' => 2
        ])->then(function (Response $response) use ($player) {
            if ($response->getStatusCode() == 200) {
                $data = json_decode($response->getBody()->getContents());

                if ($player->NickName != $data->name) {
                    self::setName($player, $data->name, true, true);
                }

                self::announceConnect($player);
            }
        }, function () use ($player) {
            //connection to service failed
            self::announceConnect($player);
        });
    }

    /**
     * @param Player $player
     * @return void
     */
    private static function announceConnect(Player $player)
    {
        $diffString = $player->last_visit->diffForHumans();

        $stats = $player->stats;

        if ($stats && !is_null($player->last_visit)) {
            $serverRank = $stats->Rank;

            if ($serverRank == -1) {
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
        self::playerPoolChanged();
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

        self::$players = self::$players->forget($player->Login);
        self::playerPoolChanged();
    }

    /**
     * Reset player ids on begin map
     *
     */
    public static function beginMap()
    {
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

        self::kickPlayerEvent($player, $playerToBeKicked->Login, implode(' ', $reason));
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
        $toBeKicked = null;

        /**
         * @var Player $toBeKicked
         */
        try {
            $toBeKicked = Player::find($login);
        } catch (\Exception $e) {
        }

        if (is_null($toBeKicked)) {
            $toBeKicked = (object)[
                'group' => (object)[
                    'security_level' => 0
                ]
            ];
        }

        if ($toBeKicked->group->security_level > $player->group->security_level) {
            warningMessage('You can not kick players with a higher security-level than yours.')->send($player);
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
                    'map_id'    => $map->id,
                    'player_id' => $player->id
                ], [
                    'score'       => $score,
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

    /**
     * @param Player $player
     * @param string $targetLogin
     * @return void
     */
    public static function forceSpecEvent(Player $player, string $targetLogin)
    {
        Server::forceSpectator($targetLogin, 3);

        infoMessage($player, ' forced ', player($targetLogin), ' into spectator mode.')->sendAll();
    }

    /**
     * @param Player $player
     * @param string $cmd
     * @param string $count
     * @return void
     */
    public static function addFakePlayer(Player $player, string $cmd, string $count = '1')
    {
        infoMessage($player, ' adds ', secondary($count), ' fake players.')->sendAll();

        if (empty($count)) {
            $count = '1';
        }

        for ($i = 0; $i < intval($count); $i++) {
            Server::connectFakePlayer();
        }
    }

    /**
     * @param Player $player
     * @return void
     */
    public static function resetUserSettings(Player $player)
    {
        $player->settings()->delete();
        infoMessage('Your settings have been cleared. You may want to call ', secondary('/reset'))->send($player);
    }

    /**
     * @param Player $admin
     * @param string $targetLogin
     * @return void
     */
    public static function allowSetname(Player $admin, string $targetLogin)
    {
        $targetPlayer = self::getPlayer($targetLogin);
        if ($targetPlayer->isSetnameBlacklisted()) {
            $targetPlayer->setnameBlacklist()->delete();

            successMessage('You are allowed to use ', secondary('/setname'), ' again.')->send($targetPlayer);
            successMessage('Removed ', $targetPlayer, ' from setname blacklist.')->send($admin);

            Hook::fire('PlayerSetnameBlacklistRemoved', $targetPlayer, $admin);
        }
    }

    /**
     * @param Player $admin
     * @param string $targetLogin
     * @return void
     */
    public static function resetName(Player $admin, string $targetLogin)
    {
        $targetPlayer = self::getPlayer($targetLogin);
        self::setName($targetPlayer, $targetPlayer->ubisoft_name, true);
        warningMessage($admin, ' resets the custom name of ', $targetPlayer)->sendAll();
    }

    /**
     * @param Player $admin
     * @param string $targetLogin
     * @param string|null $reason
     * @return void
     */
    public static function resetNameAndBlacklist(Player $admin, string $targetLogin, string $reason = null)
    {
        $targetPlayer = self::getPlayer($targetLogin);

        if ($targetPlayer->isSetnameBlacklisted()) {
            $reason = $targetPlayer->setnameBlacklist->reason;

            if ($reason) {
                $reason = ", reason: " . secondary($reason);
            }

            warningMessage('Player ', $targetPlayer, ' is already banned from using ', secondary('/setname'), $reason)->send($admin);
            return;
        }

        $blacklistEntry = SetnameBlacklist::create([
            'login'          => $targetLogin,
            'reason'         => $reason,
            'blacklisted_by' => $admin->Login
        ]);

        self::resetName($admin, $targetLogin);

        Hook::fire('PlayerSetnameBlacklisted', $blacklistEntry);
    }

    /**
     * @param Player $player
     * @param $targetLogin
     * @return void
     */
    public static function specPlayer(Player $player, $targetLogin)
    {
        Server::forceSpectator($player->Login, Server::FORCE_TO_SPECTATORS_SELECTABLE);
        usleep(100000);
        Server::forceSpectatorTarget($player->Login, $targetLogin, Server::FORCE_TO_SPECTATORS);
    }

    /**
     * @param Player $player
     * @param $targetLogin
     * @return void
     */
    public static function muteLoginToggle(Player $player, $targetLogin)
    {
        $target = player($targetLogin);

        if (ChatController::isPlayerMuted($target)) {
            ChatController::unmute($player, $target);
        } else {
            ChatController::mute($player, $target);
        }
    }

    /**
     * @param Player $player
     * @param string $targetLogin
     * @param string $message
     * @return void
     */
    public static function warnPlayer(Player $player, string $targetLogin, string $message = '')
    {
        $target = Player::whereLogin($targetLogin)->first();

        if ($target) {
            warningMessage("You have warned $target")->send($player);
            warningMessage("You have been warned by $player ", secondary($message))->send($target);
        }
    }
}
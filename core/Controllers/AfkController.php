<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;

/**
 * Class AfkController
 *
 * Automatically set afk-players to spectator.
 *
 * @package EvoSC\Controllers
 */
class AfkController implements ControllerInterface
{
    /**
     * @var Collection
     */
    private static Collection $pingTracker;

    /**
     * Initialize
     */
    public static function init()
    {
        self::$pingTracker = collect();
    }

    /**
     * @param  string  $mode
     * @param  bool  $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        Hook::add('PlayerConnect', [self::class, 'sendPinger']);
        Hook::add('PlayerDisconnect', [self::class, 'removePlayerFromTracker']);
        Hook::add('PlayerCheckpoint', [self::class, 'interaction']);

        ManiaLinkEvent::add('ping', [self::class, 'pingReceived']);

//        Timer::create('checkPing', [self::class, 'checkPing'], '30s', true);
    }

    /**
     * Add player to the tracker and send pinger
     *
     * @param Player $player
     */
    public static function sendPinger(Player $player)
    {
        Template::show($player, 'Scripts.pinger');
    }

    /**
     * Remove players from afk-tracker (when he leaves, or goes spec himself).
     *
     * @param  Player  $player
     */
    public static function removePlayerFromTracker(Player $player)
    {
        self::$pingTracker->forget($player->Login);
    }

    /**
     * Handle received ping
     *
     * @param Player $player
     * @param  int  $secondsSinceLastInteraction
     */
    public static function pingReceived(Player $player, int $secondsSinceLastInteraction)
    {
        self::$pingTracker->put($player->Login, time());

        if (($secondsSinceLastInteraction / 60) >= config('server.afk-timeout') && !$player->isSpectator()) {
            $message = infoMessage($player, ' was moved to spectators after ',
                secondary(config('server.afk-timeout').' minutes'), ' of inactivity.')->setIcon('');

            if (config('server.echoes.join')) {
                $message->sendAll();
            } else {
                $message->sendAdmin();
            }

            Server::forceSpectator($player->Login, 3);
        }
    }

    public static function interaction(Player $player)
    {
        self::$pingTracker->put($player->Login, time());
    }

    private static function revivePlayer(Player $player)
    {
//        warningMessage('EvoSC detected you as offline and is now reconnecting you.')->send($player);
//        Hook::fire('PlayerConnect', $player);
    }

    /**
     * Check the ping status for all players.
     */
    public static function checkPing()
    {
        onlinePlayers()->each(function (Player $player) {
            if (!self::$pingTracker->has($player->Login)) {
                self::$pingTracker->put($player->Login, 0);

                return;
            }

            $lastPing = self::$pingTracker->get($player->Login);

            if ($lastPing <= 0) {
                $lastPing--;

                if ($lastPing < -20) {
                    self::$pingTracker->put($player->Login, 0);
                    self::revivePlayer($player);

                    return;
                }

                self::$pingTracker->put($player->Login, $lastPing);
            }
        });
    }

    /**
     * Force a player to spectator-mode.
     *
     * @param Player $player
     * @param Player $admin
     */
    public static function forceToSpectators(Player $player, Player $admin)
    {
        Server::forceSpectator($player->Login, 3);

        infoMessage($player, ' was forced to spectators by ', $admin)->sendAll();
    }
}
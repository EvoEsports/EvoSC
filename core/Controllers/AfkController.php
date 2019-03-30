<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Classes\Timer;
use esc\Models\Player;
use esc\Interfaces\ControllerInterface;

/**
 * Class AfkController
 *
 * Automatically set afk-players to spectator.
 *
 * @package esc\Controllers
 */
class AfkController implements ControllerInterface
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $afkTracker;

    /**
     * Initialize
     */
    public static function init()
    {
        self::$afkTracker = collect();

        Hook::add('PlayerConnect', [self::class, 'interaction']);
        Hook::add('PlayerCheckpoint', [self::class, 'interaction']);
        Hook::add('PlayerFinish', [self::class, 'interaction']);
        Hook::add('PlayerStartLine', [self::class, 'interaction']);
        Hook::add('PlayerDisconnect', [self::class, 'removePlayerFromTracker']);

        Timer::create('checkAfkStatus', [self::class, 'checkAfkStatus'], '20s', true);
    }

    /**
     * Remove players from afk-tracker (when he leaves, or goes spec himself).
     *
     * @param Player $player
     */
    public static function removePlayerFromTracker(Player $player)
    {
        self::$afkTracker->forget($player->Login);
    }

    /**
     * Update the last interaction.
     *
     * @param \esc\Models\Player $player
     * @param mixed              ...$arguments
     */
    public static function interaction(Player $player, ...$arguments)
    {
        self::$afkTracker->put($player->Login, [
            'last_interaction' => now(),
            'is_afk'           => false,
        ]);
    }

    /**
     * Check the afk status for all players.
     */
    public static function checkAfkStatus()
    {
        $afkPlayers = collect();

        self::$afkTracker->each(function (array $data, string $playerLogin) use ($afkPlayers) {
            $lastInteraction = $data['last_interaction'];

            if ($lastInteraction->diffInMinutes() >= config('server.afk-timeout') && !$data['is_afk']) {
                $player = Player::whereLogin($playerLogin)->first();

                if ($player->isSpectator() ?? false) {
                    return;
                }

                $afkPlayers->push($player->NickName);

                self::$afkTracker->put($playerLogin, [
                    'last_interaction' => $lastInteraction,
                    'is_afk'           => true,
                ]);

                Server::forceSpectator($playerLogin, 3);
            }
        });

        if ($afkPlayers->count() == 1) {
            infoMessage($afkPlayers->first(), ' was moved to spectators after ', secondary(config('server.afk-timeout') . ' minutes'), ' of inactivity.')->setIcon('')->sendAll();
        } elseif ($afkPlayers->count() > 1) {
            infoMessage($afkPlayers->implode(secondary(', ')), ' were moved to spectators after ', secondary(config('server.afk-timeout') . ' minutes'), ' of inactivity.')->setIcon('')->sendAll();
        }
    }

    /**
     * Force a player to spectator-mode.
     *
     * @param \esc\Models\Player $player
     * @param \esc\Models\Player $admin
     */
    public static function forceToSpectators(Player $player, Player $admin)
    {
        Server::forceSpectator($player->Login, 3);

        infoMessage($player, ' was forced to spectators by ', $admin)->sendAll();
    }
}
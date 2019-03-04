<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Classes\Timer;
use esc\Models\Player;

class AfkController
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private static $afkTracker;

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

    public static function removePlayerFromTracker(Player $player)
    {
        self::$afkTracker->forget($player->Login);
    }

    public static function interaction(Player $player, ...$arguments)
    {
        self::$afkTracker->put($player->Login, [
            'last_interaction' => now(),
            'is_afk'           => false,
        ]);
    }

    public static function checkAfkStatus()
    {
        self::$afkTracker->each(function (array $data, string $playerLogin) {
            $lastInteraction = $data['last_interaction'];

            if ($lastInteraction->diffInMinutes() >= config('server.afk-timeout') && !$data['is_afk']) {
                $player = Player::whereLogin($playerLogin)->first();

                if ($player->isSpectator()) {
                    return;
                }

                self::$afkTracker->put($playerLogin, [
                    'last_interaction' => $lastInteraction,
                    'is_afk'           => true,
                ]);

                Server::forceSpectator($playerLogin, 3);

                $player = Player::where('Login', $playerLogin)->first();

                infoMessage($player, ' was moved to spectators after ', secondary(config('server.afk-timeout') . ' minutes'), ' of inactivity.')
                    ->setIcon('')
                    ->sendAll();
            }
        });
    }

    public static function forceAfk(Player $player, Player $admin)
    {
        Server::forceSpectator($player->Login, 3);

        infoMessage($player, ' was forced to spectators by ', $admin)
            ->sendAll();
    }
}
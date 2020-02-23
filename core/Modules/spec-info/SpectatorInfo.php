<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class SpectatorInfo implements ModuleInterface
{
    private static $specTargets;

    public function __construct()
    {
        self::$specTargets = collect();

        // Hook::add('SpecStart', [self::class, 'specStart']);
        // Hook::add('SpecStop', [self::class, 'specStop']);
    }

    public static function specStart(Player $player, Player $target)
    {
        self::$specTargets->put($player->Login, $target);
        self::updateWidget($target);
    }

    public static function specStop(Player $player, Player $target)
    {
        Template::show($player, 'spec-info.hide');
        self::$specTargets->put($player->Login, null);
        self::updateWidget($target);
    }

    public static function updateWidget(Player $player)
    {
        $spectatorLogins = self::$specTargets->filter(function ($target) use ($player) {
            if ($target instanceof Player) {
                return $target->Login == $player->Login;
            }
        })->take(10)->keys();

        $spectators = Player::whereIn('Login', $spectatorLogins)->get();

        if ($spectators->count() > 0) {
            Template::show($player, 'spec-info.widget', compact('spectators'));

            $spectators->each(function (Player $player) use ($spectators) {
                Template::show($player, 'spec-info.widget', compact('spectators'));
            });
        } else {
            Template::show($player, 'spec-info.hide');
        }
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}
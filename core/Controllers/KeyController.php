<?php

namespace esc\Controllers;


use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Models\Player;
use Illuminate\Support\Collection;

class KeyController
{
    private static $binds;

    public static function init()
    {
        self::$binds = collect();

        Hook::add('PlayerConnect', [KeyController::class, 'playerConnect']);

        ManiaLinkEvent::add('keybind', [KeyController::class, 'executeBinds']);
    }

    public static function createBind(string $key, array $function)
    {
        $bind = collect([]);

        $bind->key = $key;
        $bind->function = $function;

        self::$binds->push($bind);
    }

    public static function executeBinds(Player $player, string $key)
    {
        $binds = self::$binds->where('key', $key);

        if ($binds->count() == 0) {
            return;
        }

        foreach ($binds as $bind) {
            call_user_func($bind->function, $player);
        }
    }

    public static function sendKeybindsScript(Player $player)
    {
        $keys = self::$binds->pluck('key');

        if (count($keys) == 0) {
            return;
        }

        Template::show($player, 'keybinds', compact('keys'));
    }

    public static function playerConnect(Player $player)
    {
        self::sendKeybindsScript($player);
    }

    public static function getKeybinds(): Collection
    {
        return self::$binds;
    }
}
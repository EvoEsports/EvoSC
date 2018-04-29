<?php

namespace esc\Controllers;


use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Models\Player;

class KeyController
{
    private static $binds;

    public static function init()
    {
        self::$binds = collect([]);

        Hook::add('PlayerConnect', 'KeyController::playerConnect');

        ManiaLinkEvent::add('keybind', 'KeyController::executeBinds');

        Template::add('keybinds', File::get('core/Templates/keybinds.latte.xml'));

        foreach (onlinePlayers() as $player) {
            self::sendKeybindsScript($player);
        }
    }

    public static function createBind(string $key, string $function)
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
            call_user_func_array($bind->function, [$player]);
        }
    }

    public static function sendKeybindsScript(Player $player)
    {
        $keys = self::$binds->pluck('key');

        if(count($keys) == 0){
            return;
        }

        Template::show($player, 'keybinds', compact('keys'));
    }

    public static function playerConnect(Player $player)
    {
        self::sendKeybindsScript($player);
    }
}
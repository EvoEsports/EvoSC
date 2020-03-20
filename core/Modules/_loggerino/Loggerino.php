<?php


namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\AccessRight;
use esc\Models\Player;
use Illuminate\Support\Collection;

class Loggerino extends Module implements ModuleInterface
{
    private static Collection $listeners;
    private static Collection $lines;
    private static int $rows = 18;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$lines = collect();
        self::$listeners = collect();

        InputSetup::add('loggerino.start', 'Start live log.', [self::class, 'sendManialink'], 'F8', 'log');

        Hook::add('PlayerDisconnect', [self::class, 'removePlayerFromListeners']);

        AccessRight::createIfMissing('log', 'View the server log.');
    }

    public static function append(string $line)
    {
        if (!isset(self::$lines)) {
            return;
        }

        self::$lines->push($line);

        while (self::$lines->count() > self::$rows) {
            self::$lines->shift();
        }

        foreach (self::$listeners as $listener) {
            Template::show($listener, '_loggerino.update', ['lines' => self::$lines]);
        }
    }

    public static function sendManialink(Player $player)
    {
        Template::show($player, '_loggerino.window', ['rows' => self::$rows]);
        self::$listeners->put($player->Login, $player);
    }

    public static function removePlayerFromListeners(Player $player)
    {
        self::$listeners->forget($player->Login);
    }
}
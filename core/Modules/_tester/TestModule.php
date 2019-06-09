<?php


namespace esc\Modules;


use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use Illuminate\Support\Collection;

class TestModule
{
    public function __construct()
    {
        KeyBinds::add('test_stuff', '', [self::class, 'testStuff'], 'X', 'ma');
        self::testStuff();
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();

        $testLogins = collect(Server::getPlayerList())->pluck('login');
        $start      = microtime(true) + time();
        $players    = Player::whereIn('Login', $testLogins)->get();
        $end        = microtime(true) + time();
        printf("Took %.3fs\n", ($duration = $end - $start));
    }
}
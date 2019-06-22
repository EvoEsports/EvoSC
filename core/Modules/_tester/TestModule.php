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
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();
        LiveRankings::playerConnect($player);
    }
}
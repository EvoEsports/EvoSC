<?php


namespace esc\Modules;


use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\CountdownController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class TestModule
{
    public function __construct()
    {
        InputSetup::add('test_stuff', 'Trigger TestModule::testStuff', [self::class, 'testStuff'], 'X', 'ma');
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();
        var_dump(CountdownController::getOriginalTimeLimit(), CountdownController::getSecondsLeft());
    }

    public static function sendTestManialink(Player $player)
    {
        Template::show($player, '_tester.test');
    }
}
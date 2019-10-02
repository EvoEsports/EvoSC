<?php


namespace esc\Modules;


use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class TestModule
{
    public function __construct()
    {
        KeyBinds::add('test_stuff', 'Trigger TestModule::testStuff', [self::class, 'testStuff'], 'X', 'ma');
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();
        MxPackLoader::showAddMapPack($player, '', '100');
    }

    public static function sendTestManialink(Player $player)
    {
        Template::show($player, '_tester.test');
    }
}
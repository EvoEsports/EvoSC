<?php


namespace esc\Modules;


use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use esc\Modules\LocalRecords\LocalRecords;

class TestModule
{
    public function __construct()
    {
        KeyBinds::add('test_stuff', 'Trigger TestModule::testStuff', [self::class, 'testStuff'], 'X', 'ma');
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();
        LocalRecords::sendLocalsChunk($player);
        LocalRecords::showManialink($player);
    }

    public static function sendTestManialink(Player $player)
    {
        Template::show($player, '_tester.test');
    }
}
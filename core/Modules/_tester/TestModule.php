<?php


namespace esc\Modules;


use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use esc\Modules\MusicClient\MusicClient;

class TestModule
{
    public function __construct()
    {
        KeyBinds::add('test_stuff', 'Trigger TestModule::testStuff', [self::class, 'testStuff'], 'X', 'ma');
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();
        $map = MapController::getCurrentMap();
        RecordsTable::showGraph($player, $map->id, 'Dedimania Records', $map->dedis()->inRandomOrder()->first()->id);
    }

    public static function sendTestManialink(Player $player)
    {
        Template::show($player, '_tester.test');
    }
}
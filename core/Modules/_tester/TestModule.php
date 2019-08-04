<?php


namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Player;
use esc\Modules\MusicClient\MusicClient;
use Illuminate\Support\Collection;

class TestModule
{
    public function __construct()
    {
        KeyBinds::add('test_stuff', 'Trigger TextModule::testStuff', [self::class, 'testStuff'], 'X', 'ma');
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();
        UiSettings::mleShowSettingsWindow($player);
    }
}
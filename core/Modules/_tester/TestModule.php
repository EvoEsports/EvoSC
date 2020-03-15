<?php


namespace esc\Modules;


use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class TestModule extends Module implements ModuleInterface
{
    public function __construct()
    {
        InputSetup::add('test_stuff', 'Trigger TestModule::testStuff', [self::class, 'testStuff'], 'X', 'ma');

        ManiaLinkEvent::add('asdf', [self::class, 'sendaction_for_Controller']);
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();
        dd(MapController::getGbxInformation(Map::first()->filename, false));
    }

    public static function sendTestManialink(Player $player)
    {
        Template::show($player, '_tester.test');
    }

    public static function sendaction_for_Controller(Player $player, Collection $formData = null)
    {
        var_dump($player->Login, $formData);
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}
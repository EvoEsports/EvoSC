<?php


namespace esc\Modules;


use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class TestModule
{
    public function __construct()
    {
        // KeyBinds::add('test_stuff', '', [self::class, 'testStuff'], 'X', 'ma');
    }

    public static function testStuff(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, '_tester.test');
    }
}
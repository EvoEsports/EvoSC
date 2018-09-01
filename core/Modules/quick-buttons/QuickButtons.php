<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class QuickButtons
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showButtons']);

        ManiaLinkEvent::add('time.add', [self::class, 'addTime'], 'time');

        KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::showButtons($player);
    }

    public static function showButtons(Player $player)
    {
        Template::show($player, 'quick-buttons.overlay');
    }

    public static function addTime(Player $player, $time)
    {
        MapController::addTimeManually($player, "addtime", intval($time));
    }
}
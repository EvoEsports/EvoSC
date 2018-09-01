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
    private static $buttons;

    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showButtons']);

        ManiaLinkEvent::add('time.add', [self::class, 'addTime'], 'time');

        KeyController::createBind('Y', [self::class, 'reload']);
        KeyController::createBind('Q', [self::class, 'addOne'], 'time');

        self::addButton('', '+5 min', 'time.add,5', 'time');
        self::addButton('', '+10 min', 'time.add,10', 'time');
        self::addButton('', '+15 min', 'time.add,15', 'time');
    }

    public static function addButton(string $icon, string $text, string $maniaLinkAction, string $access = '')
    {
        if (!self::$buttons) {
            self::$buttons = collect();
        }

        $button = collect();
        $button->icon = $icon;
        $button->text = $text;
        $button->action = $maniaLinkAction;
        $button->access = $access;

        self::$buttons->push($button);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::showButtons($player);
    }

    public static function showButtons(Player $player)
    {
        $buttons = self::$buttons->filter(function ($button) use ($player) {
            //Only get buttons the player has access to
            return $player->hasAccess($button->action);
        })->map(function ($button) {
            //convert into maniascript format
            return sprintf('["%s", "%s", "%s"]', $button->icon, $button->text, $button->action);
        })->implode(',');

        Template::show($player, 'quick-buttons.overlay', compact('buttons'));
    }

    public static function addOne(Player $player)
    {
        self::addTime($player, 1);
    }

    public static function addTime(Player $player, $time)
    {
        MapController::addTimeManually($player, "addtime", intval($time));
    }
}
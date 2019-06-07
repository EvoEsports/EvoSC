<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Models\Player;

class QuickButtons
{
    private static $buttons;

    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showButtons']);
        Hook::add('GroupChanged', [self::class, 'showButtons']);
    }

    public static function addButton(string $icon, string $text, string $maniaLinkAction, string $access = '')
    {
        if (!self::$buttons) {
            self::$buttons = collect();
        }

        $button         = collect();
        $button->icon   = $icon;
        $button->text   = $text;
        $button->action = $maniaLinkAction;
        $button->access = $access;

        self::$buttons->push($button);
    }

    public static function showButtons(Player $player)
    {
        $buttons = self::$buttons->filter(function ($button) use ($player) {
            if (!$button->access) {
                //No access limitation
                return true;
            }

            //Only get buttons the player has access to
            return $player->hasAccess($button->access);
        })->map(function ($button) {
            //convert into maniascript format
            return sprintf('["%s", "%s", "%s"]', $button->icon, $button->text, $button->action);
        })->implode(',');

        Template::show($player, 'quick-buttons.overlay', compact('buttons'));
    }
}
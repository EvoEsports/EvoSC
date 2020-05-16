<?php

namespace EvoSC\Modules\QuickButtons;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;

class QuickButtons extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static Collection $buttons;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'showButtons']);
        Hook::add('GroupChanged', [self::class, 'showButtons']);
    }

    public static function addButton(string $icon, string $text, string $maniaLinkAction, string $access = "")
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

    public static function showButtons(Player $player)
    {
        $buttons = self::$buttons->filter(function ($button) use ($player) {
            if (!$button->access) {
                //No access limitation
                return true;
            }

            //Only get buttons the player has access to
            return $player->hasAccess($button->access);
        });

        Template::show($player, 'quick-buttons.overlay', compact('buttons'));
    }

    public static function removeAll()
    {
        self::$buttons = collect();
    }
}
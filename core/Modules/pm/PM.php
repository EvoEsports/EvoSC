<?php


namespace esc\Modules;


use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class PM extends Module implements ModuleInterface
{

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ManiaLinkEvent::add('pm.dialog', [self::class, 'mlePmDialog']);
        ManiaLinkEvent::add('pm', [self::class, 'mlePm']);
    }

    public static function mlePmDialog(Player $player, string $recipientLogin)
    {
        Template::show($player, 'pm.dialog', compact('recipientLogin'));
    }

    public static function mlePm(Player $player, string $recipientLogin, string ...$text)
    {
        ChatController::pmTo($player, $recipientLogin, implode(', ', $text));
    }
}
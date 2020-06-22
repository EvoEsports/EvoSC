<?php


namespace EvoSC\Modules\PM;


use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ChatController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

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
        Template::show($player, 'PM.dialog', compact('recipientLogin'));
    }

    public static function mlePm(Player $player, string $recipientLogin, string ...$text)
    {
        ChatController::pmTo($player, $recipientLogin, implode(', ', $text));
    }
}
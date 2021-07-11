<?php


namespace EvoSC\Modules\PM;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ChatController;
use EvoSC\Controllers\PlayerController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class PM extends Module implements ModuleInterface
{

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ManiaLinkEvent::add('pm.dialog', [self::class, 'mlePmDialog']);
        ManiaLinkEvent::add('pm', [self::class, 'mlePm']);

        ChatCommand::add('/pm', [self::class, 'cmdWritePM'], 'Send a private message to another player. Usage: /pm <partial_nickname> <message...> or click PM in the scoreboard, to message a player.')
        ->addAlias('dm');
    }

    /**
     * @param Player $player
     * @param $cmd
     * @param $target
     * @param mixed ...$text
     */
    public static function cmdWritePM(Player $player, $cmd, $target, ...$text)
    {
        $target = PlayerController::findPlayerByName($player, $target);

        if($target){
            self::mlePm($player, $target->Login, ...$text);
        }
    }

    /**
     * @param Player $player
     * @param string $recipientLogin
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function mlePmDialog(Player $player, string $recipientLogin)
    {
        Template::show($player, 'PM.dialog', compact('recipientLogin'));
    }

    /**
     * @param Player $player
     * @param string $recipientLogin
     * @param string ...$text
     */
    public static function mlePm(Player $player, string $recipientLogin, string ...$text)
    {
        ChatController::pmTo($player, $recipientLogin, '$z'.implode(' ', $text));
    }
}
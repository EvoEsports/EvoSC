<?php


namespace EvoSC\Modules\MemeMod;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;

class MemeMod extends Module implements ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerChat', [self::class, 'chat']);
        InputSetup::add('pay_respects', 'Pay your respects', [self::class, 'payRespects'], 'F');
    }

    /**
     * @param Player $player
     * @param string $text
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function chat(Player $player, string $text)
    {
        if (!preg_match('/^praise the sun/i', $text)) {
            return;
        }

        Template::show($player, 'MemeMod.darksouls');
        dangerMessage('DarkSouls-Mode enabled!')->send($player);
        infoMessage($player, ' praises the sun.')->sendAll();
    }

    /**
     * @param Player $player
     */
    public static function payRespects(Player $player)
    {
        infoMessage($player, ' pays his respects.')->sendAll();
    }
}
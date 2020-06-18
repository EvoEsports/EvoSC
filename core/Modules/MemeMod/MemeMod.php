<?php


namespace EvoSC\Modules\MemeMod;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
use Illuminate\Support\Collection;

class MemeMod extends Module implements ModuleInterface
{
    private static Collection $tracker;

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$tracker = collect();

        Hook::add('PlayerChat', [self::class, 'chat']);
        Hook::add('PlayerDisconnect', [self::class, 'playerDisconnect']);
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
        if (self::$tracker->has($player->id)) {
            if (($timePassed = time() - self::$tracker->get($player->id)) < 10) {
                $timeLeft = 10 - $timePassed;
                warningMessage('You\'ve just paid your respects. Please wait ', secondary($timeLeft . 's'), ' before paying your respects again.')->send($player);
                return;
            }
        }

        infoMessage($player, ' paid her/his respects.')->sendAll();

        self::$tracker->put($player->id, time());
    }

    public static function playerDisconnect(Player $player)
    {
        self::$tracker->forget($player->id);
    }
}
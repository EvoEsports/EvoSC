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
        $tracker = self::$tracker->get($player->id);
        $time = 0;

        if (is_null($tracker)) {
            $tracker = (object)['last' => 0, 'total' => 0];
        } else if ($tracker->total > 100) {
            self::stopSpam($player);
            $tracker->total = 0;
        } else {
            $time = $tracker->last;
        }

        if (($timePassed = time() - $tracker->last) < 3) {
            warningMessage('You\'ve just paid your respects. Please wait ', secondary((3 - $timePassed) . 's'), ' before paying your respects again.')->send($player);
        } else {
            infoMessage(secondary($player), ' pays their respects.')->sendAll();
            $time = time();
        }

        self::$tracker->put($player->id, (object)[
            'last' => $time,
            'total' => $tracker->total + 1
        ]);
    }

    private static function stopSpam(Player $player)
    {
        $newBindJson = json_encode([
            'code' => 41,
            'name' => 'F4',
            'def' => 'F',
            'id' => 'pay_respects'
        ]);

        dangerMessage('You have paid too many respects. The key to pay respects have been rebound to ', secondary('F4'), '. You can change it back in Input-Setup.')->send($player);

        InputSetup::updateBind($player, ...explode(',', $newBindJson));
        InputSetup::sendScript($player);
    }

    public static function playerDisconnect(Player $player)
    {
        self::$tracker->forget($player->id);
    }
}
<?php


namespace EvoSC\Modules\MemeMod;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ChatController;
use EvoSC\Controllers\PlayerController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\PlayerContextMenu\PlayerContextMenu;
use Illuminate\Support\Collection;

class MemeMod extends Module implements ModuleInterface
{
    private static Collection $tracker;

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$tracker = collect();

        Hook::add('PlayerDisconnect', [self::class, 'playerDisconnect']);
        InputSetup::add('pay_respects', 'Pay your respects', [self::class, 'payRespects'], 'F');
        ChatCommand::add('/mock', [self::class, 'mockingSpongebobText'], 'Write mocking spongebob text to chat.');
        ManiaLinkEvent::add('rebind_f_key', [self::class, 'mleRebindFKey'], 'player_mute');
        PlayerContextMenu::extend('', 'Rebind F key', 'rebind_f_key', 'player_mute', true);

        if (isManiaPlanet()) {
            Hook::add('PlayerChat', [self::class, 'chat']);
        }
    }

    /**
     * @param Player $player
     * @param string $login
     * @return void
     */
    public static function mleRebindFKey(Player $player, string $login)
    {
        self::stopSpam(PlayerController::getPlayer($login));

        successMessage('Rebound players ', secondary('F'), ' key.')->send($player);
    }

    /**
     * @param Player $player
     * @param $cmd
     * @param ...$text
     * @return void
     */
    public static function mockingSpongebobText(Player $player, $cmd, ...$text)
    {
        $out = '';
        foreach ($text as $part) {
            $out .= self::mock($part) . ' ';
        }

        ChatController::playerChat($player, trim($out));
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

        if (ChatController::isPlayerMuted($player)) {
            return;
        }

        infoMessage($player, ' praises the sun.')->sendAll();
    }

    /**
     * @param Player $player
     */
    public static function payRespects(Player $player)
    {
        if (ChatController::isPlayerMuted($player)) {
            return;
        }

        $tracker = self::$tracker->get($player->id);

        if (is_null($tracker)) {
            $tracker = collect();
        }

        if (self::isSpamming($tracker)) {
            self::stopSpam($player);
            return;
        }

        infoMessage(secondary($player), ' pays their respects.')->sendAll();

        $tracker->push(time());

        if ($tracker->count() > 5) {
            $tracker->shift();
        }

        self::$tracker->put($player->id, $tracker);
    }

    /**
     * @param Player $player
     * @return void
     */
    public static function playerDisconnect(Player $player)
    {
        self::$tracker->forget($player->id);
    }

    /**
     * @param Collection $entries
     * @return bool
     */
    private static function isSpamming(Collection $entries): bool
    {
        $threshold = 3;
        $underLimit = 0;

        foreach ($entries as $entry) {
            if (time() - $entry < $threshold) {
                $underLimit++;
            }
        }

        return $underLimit == $threshold;
    }

    /**
     * @param Player $player
     * @return void
     */
    private static function stopSpam(Player $player)
    {
        $newBindJson = json_encode([
            'code' => 41,
            'name' => 'F4',
            'def'  => 'F',
            'id'   => 'pay_respects'
        ]);

        dangerMessage('You have paid too many respects. The key to pay respects have been rebound to ', secondary('F4'), '. You can change it back in Input-Setup.')->send($player);

        InputSetup::updateBind($player, ...explode(',', $newBindJson));
        InputSetup::sendScript($player);
    }

    /**
     * @param string $in
     * @return string
     */
    private static function mock(string $in)
    {
        $out = '';
        $split = str_split($in);
        foreach ($split as $i => $c) {
            if ($i % 2 == 0) {
                $out .= strtolower($c);
            } else {
                $out .= strtoupper($c);
            }
        }
        return $out;
    }
}
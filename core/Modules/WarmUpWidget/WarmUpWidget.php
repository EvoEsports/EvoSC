<?php


namespace EvoSC\Modules\WarmUpWidget;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;

class WarmUpWidget extends Module implements ModuleInterface
{
    private static int $round = 0;
    private static int $warmUpNb = 0;
    private static bool $warmUpInProgress = false;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        AccessRight::add('warm_up_skip', 'Lets you skip the warm-up phase.');

        Hook::add('PlayerConnect', [self::class, 'sendWarmUpWidget']);
        Hook::add('WarmUpEnd', [self::class, 'warmUpEnd']);
        Hook::add('Trackmania.WarmUp.StartRound', [self::class, 'warmUpStartRound']);

        self::$warmUpNb = Server::getModeScriptSettings()['S_WarmUpNb'];

        ManiaLinkEvent::add('warmup.skip', [self::class, 'skipWarmUp'], 'warm_up_skip');
    }

    public static function sendWarmUpWidget(Player $player)
    {
        if(self::$warmUpInProgress){
            Template::show($player, 'WarmUpWidget.widget', [
                'warmupNb' => self::$warmUpNb,
                'round' => ++self::$round
            ]);
        }
    }

    public static function warmUpStartRound()
    {
        self::$warmUpInProgress = true;

        Template::showAll('WarmUpWidget.widget', [
            'warmupNb' => self::$warmUpNb,
            'round' => ++self::$round
        ]);

        if (self::$warmUpNb == 1) {
            $message = [
                'Warm-up ',
                secondary(self::$round . '/' . self::$warmUpNb),
                ' started.'
            ];
        } else {
            if (self::$warmUpNb == self::$round) {
                $message = [
                    secondary('Last warm-up'),
                    ' started.'
                ];
            } else {
                $message = [
                    'Warm-up ',
                    secondary(self::$round . '/' . self::$warmUpNb),
                    ' started.'
                ];
            }
        }

        infoMessage(...$message)->setColor('f90')->setIcon(' ')->sendAll();
    }

    public static function warmUpEnd()
    {
        self::$warmUpInProgress = false;
        self::$round = 0;

        infoMessage('Warm-up ended, ', secondary('starting play-loop.'))->setColor('f90')->setIcon(' ')->sendAll();
        Template::showAll('WarmUpWidget.widget', ['warmUpEnded' => true, 'warmupNb' => 0, 'round' => 0]);
    }

    public static function skipWarmUp(Player $player)
    {
        Server::triggerModeScriptEventArray('Trackmania.WarmUp.ForceStop', []);
        infoMessage($player, ' skips warm-up.')->setColor('f90')->sendAll();
        self::warmUpEnd();
    }

    public static function setWarmUpLimit(int $seconds)
    {
        $settings = Server::getModeScriptSettings();
        $settings['S_WarmUpDuration'] = $seconds;
        Server::setModeScriptSettings($settings);
    }
}
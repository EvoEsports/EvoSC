<?php


namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\MatchSettingsController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class WarmUpWidget implements ModuleInterface
{

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function start(string $mode)
    {
        Hook::add('WarmUpStart', [self::class, 'showWarmUpWidget']);
        Hook::add('WarmUpEnd', [self::class, 'hideWarmUpWidget']);
        Hook::add('EndMatch', [self::class, 'hideWarmUpWidget']);

        ManiaLinkEvent::add('warmup.skip', [self::class, 'skipWarmUp']);
    }

    public static function showWarmUpWidget()
    {
        Template::showAll('warmup-widget.widget');
    }

    public static function hideWarmUpWidget()
    {
        Template::showAll('warmup-widget.widget', ['warmUpEnded' => true]);
    }

    public static function skipWarmUp(Player $player)
    {
        Server::setWarmUp(false);
        Server::setAllWarmUpDuration(0);
        Server::setCupWarmUpDuration(0);
        infoMessage($player, ' skips warm-up.')->setColor('f90')->sendAll();
        self::hideWarmUpWidget();
    }
}
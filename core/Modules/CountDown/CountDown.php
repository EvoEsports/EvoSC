<?php

namespace EvoSC\Modules\CountDown;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\CountdownController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;

class CountDown extends Module implements ModuleInterface
{
    /**
     * @var string $state
     */
    private static string $state = 'play';

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'showCountdown']);
        Hook::add('AddedTimeChanged', [self::class, 'addedTimeChanged']);
        Hook::add('EndMatch', [self::class, 'matchEnded']);
        Hook::add('BeginMap', [self::class, 'mapBegun']);
        Hook::add('BeginMatch', [self::class, 'mapBegun']);

        ManiaLinkEvent::add('cd.skip_scores', [self::class, 'mleSkipScores']);
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showCountdown(Player $player)
    {
        $addedTime = round(CountdownController::getAddedSeconds() / 60, 1);
        $skipAccess = $player->hasAccess('map_skip');

        Template::show($player, 'CountDown.state', [
            'state'    => self::$state,
            'chatTime' => Server::getModeScriptSetting('S_ChatTime')
        ]);
        Template::show($player, 'CountDown.widget' . (isTrackmania() ? '_2020' : ''), compact('skipAccess'));
        Template::show($player, 'CountDown.update-added-time', compact('addedTime'));
    }

    /**
     * @param $addedSeconds
     */
    public static function addedTimeChanged($addedSeconds)
    {
        $addedTime = round($addedSeconds / 60, 1);
        Template::showAll('CountDown.update-added-time', compact('addedTime'));
    }

    public static function matchEnded()
    {
        self::$state = 'scores';
        $chatTime = Server::getModeScriptSetting('S_ChatTime');

        Template::showAll('CountDown.state', [
            'state'    => self::$state,
            'chatTime' => $chatTime
        ]);
    }

    public static function mapBegun(Map $map = null)
    {
        self::$state = 'play';

        Template::showAll('CountDown.state', [
            'state'    => self::$state,
            'chatTime' => 0
        ]);
    }

    public static function mleSkipScores(Player $player)
    {
        $settings = Server::getModeScriptSettings();
        $originalChatTime = $settings['S_ChatTime'];
        $settings['S_ChatTime'] = -1;
        Server::setModeScriptSettings($settings);

        infoMessage($player, ' forced the map change.')->sendAll();

        $settings['S_ChatTime'] = $originalChatTime;
        Server::setModeScriptSettings($settings);
    }
}
<?php

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\controllers\MapController;
use esc\Models\Player;

class TimeWidget
{
    public function __construct()
    {
        Template::add('time-widget', File::get(__DIR__ . '/Templates/time-widget.latte.xml'));

        Hook::add('PlayerConnect', 'TimeWidget::show');
        Hook::add('EndMatch', 'TimeWidget::hide');

        ManiaLinkEvent::add('tw.addTime', 'TimeWidget::addTime');
        ManiaLinkEvent::add('tw.requestMoreTime', 'TimeWidget::requestMoreTime');

        TimeWidget::show();
    }

    public static function show(Player $player = null)
    {
        if ($player) {
            Template::show($player, 'time-widget');
        } else {
            Template::showAll('time-widget');
        }
    }

    public static function hide()
    {
        Template::hideAll('time-widget');
    }

    public static function addTime(Player $player)
    {
        if ($player->isAdmin()) {
            MapController::addTime(1);
        }
    }

    public static function requestMoreTime(Player $player)
    {
        \esc\Classes\Vote::replayMap($player);
    }
}
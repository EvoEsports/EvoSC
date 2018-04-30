<?php

namespace esc\Modules\TimeWidget;

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Classes\Vote;
use esc\Controllers\MapController;
use esc\Models\Player;

class TimeWidget
{
    public function __construct()
    {
        Hook::add('PlayerConnect', 'TimeWidget::show');
        Hook::add('BeginMatch', 'TimeWidget::show');
        Hook::add('EndMatch', 'TimeWidget::hide');

        ManiaLinkEvent::add('tw.addTime', 'TimeWidget::addTime');
        ManiaLinkEvent::add('tw.requestMoreTime', 'TimeWidget::requestMoreTime');

        TimeWidget::show();
    }

    public static function show(Player $player = null)
    {
        if ($player) {
            Template::show($player, 'time-widget.time-widget');
        } else {
            Template::showAll('time-widget.time-widget');
        }
    }

    public static function hide()
    {
        Template::hideAll('time-widget.time-widget');
    }

    public static function addTime(Player $player)
    {
        if ($player->isAdmin()) {
            MapController::addTime(1);
        }
    }

    public static function requestMoreTime(Player $player)
    {
        Vote::replayMap($player);
    }
}
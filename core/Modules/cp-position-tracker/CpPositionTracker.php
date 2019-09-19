<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class CpPositionTracker implements ModuleInterface
{
    public static function showManialink(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'cp-position-tracker.manialink');
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if ($mode == 'Rpg') {
            Hook::add('PlayerConnect', [self::class, 'showManialink']);
        } else {
            Template::hideAll('cp-position-tracker');
        }
    }
}
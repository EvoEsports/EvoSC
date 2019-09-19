<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class CountDown implements ModuleInterface
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showCountdown']);
    }

    public static function showCountdown(Player $player)
    {
        Template::show($player, 'countdown.widget');
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}
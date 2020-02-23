<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class Patreon implements ModuleInterface
{
    public function __construct()
    {
        if(config('patreon.url')){
            Hook::add('PlayerConnect', [self::class, 'show']);
        }
    }

    public static function show(Player $player)
    {
        Template::show($player, 'patreon-button.widget');
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}
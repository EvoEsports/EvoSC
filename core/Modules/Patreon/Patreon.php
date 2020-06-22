<?php

namespace EvoSC\Modules\Patreon;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class Patreon extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if(config('patreon.url')){
            Hook::add('PlayerConnect', [self::class, 'show']);
        }
    }

    public static function show(Player $player)
    {
        Template::show($player, 'Patreon.widget');
    }
}
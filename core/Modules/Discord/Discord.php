<?php

namespace EvoSC\Modules\Discord;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class Discord extends Module implements ModuleInterface
{

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if(config('discord.url')){
            Hook::add('PlayerConnect', [self::class, 'show']);
        }
    }

    /**
     * @param Player $player
     */
    public static function show(Player $player)
    {
        Template::show($player, 'Discord.widget');
    }
}
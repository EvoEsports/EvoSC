<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class Discord
{
    public function __construct()
    {
        if(config('discord.url')){
            Hook::add('PlayerConnect', [Patreon::class, 'show']);
        }
    }

    public static function show(Player $player)
    {
        Template::show($player, 'discord.widget');
    }
}
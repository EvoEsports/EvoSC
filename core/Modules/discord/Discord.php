<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class Discord
{
    public function __construct()
    {
        Hook::add('PlayerConnect', 'Discord::show');
    }

    public static function show(Player $player)
    {
        Template::show($player, 'discord.widget');
    }
}
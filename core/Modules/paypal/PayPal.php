<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class PayPal
{
    public function __construct()
    {
        Hook::add('PlayerConnect', 'PayPal::show');
    }

    public static function show(Player $player)
    {
        Template::show($player, 'paypal.widget');
    }
}
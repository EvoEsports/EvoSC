<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class PayPal
{
    public function __construct()
    {
        Hook::add('PlayerConnect', 'PayPal::show');

        KeyController::createBind('X', 'PayPal::reload');
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::show($player);
    }

    public static function show(Player $player)
    {
        Template::show($player, 'paypal.widget');
    }
}
<?php

namespace esc\Modules;


use esc\Classes\Config;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class PayPal
{
    public function __construct()
    {
        if (config('paypal.url')) {
            Hook::add('PlayerConnect', [PayPal::class, 'show']);
        }
    }

    public static function reload(Player $player)
    {
        Config::loadConfigFiles();
        TemplateController::loadTemplates();
        self::show($player);
    }

    public static function show(Player $player)
    {
        Template::show($player, 'paypal.widget');
    }
}
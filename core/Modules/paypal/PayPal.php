<?php

namespace esc\Modules;


use esc\Classes\Config;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class PayPal
{
    public function __construct()
    {
        if (config('paypal.url')) {
            Hook::add('PlayerConnect', [self::class, 'show']);
        }

        ManiaLinkEvent::add('rickroll', [self::class, 'rickroll']);
    }

    public static function rickroll(Player $player)
    {
        File::appendLine(cacheDir('rickrolled.txt'), stripAll(now() . ' ' . $player->Login . ' - ' . $player));
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
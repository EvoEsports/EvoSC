<?php


namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class ClearCacheNotice
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'showNotice']);
    }

    public function showNotice(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'clear-cache-notice.window');
    }
}
<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Script;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\InfoMessage;
use esc\Models\Player;

class InfoMessages
{
    public function __construct()
    {
        ChatCommand::add('messages', [InfoMessages::class, 'showSettings'], 'Set up recurring server messages', '//', 'messages');

        KeyController::createBind('X', [self::class, 'reload']);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        Template::show($player, 'info-messages.manialink');
    }
}
<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\InfoMessage;
use esc\Models\Player;

class InfoMessages
{
    public function __construct()
    {
        ChatCommand::add('messages', 'InfoMessages::showSettings', 'Set up recurring server messages', '//', 'messages');

        ManiaLinkEvent::add('infomessages.show', 'InfoMessages::showSettings');
        ManiaLinkEvent::add('infomessages.edit', 'InfoMessages::showEdit');

        KeyController::createBind('X', 'InfoMessages::reload');
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::showSettings($player, null);
    }

    public static function showSettings(Player $player, $cmd = null)
    {
        $settings = Template::toString('info-messages.settings', []);

        Template::show($player, 'components.modal', [
            'id'            => 'infomessages.settings',
            'title'         => 'Info-messages settings',
            'width'         => 124,
            'height'        => 60,
            'content'       => $settings,
            'showAnimation' => true
        ]);
    }

    public static function showEdit(Player $player, int $id)
    {
        if ($id != -1) {
            $message = InfoMessage::find($id);
        } else {
            $message     = new InfoMessage();
            $message->id = -1; //-1 = new
        }

        $settings = Template::toString('info-messages.edit', compact('message'));

        Template::show($player, 'components.modal', [
            'id'            => 'infomessages.settings',
            'title'         => 'Edit info-message',
            'width'         => 124,
            'height'        => 44,
            'content'       => $settings,
            'showAnimation' => true,
            'onClose'       => 'infomessages.show'
        ]);
    }
}
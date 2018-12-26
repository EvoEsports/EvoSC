<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\InfoMessage;
use esc\Models\Player;

class InfoMessages
{
    public function __construct()
    {
        ChatCommand::add('messages', [InfoMessages::class, 'showSettings'], 'Set up recurring server messages', '//', 'messages');

        ManiaLinkEvent::add('info.add', [self::class, 'add'], 'info_messages');
        ManiaLinkEvent::add('info.update', [self::class, 'update'], 'info_messages');

        foreach (InfoMessage::all() as $message) {
            Timer::create('info_message_' . $message, function () use ($message) {
                ChatController::message(onlinePlayers(), '_info', $message->text);
            }, $message->delay . 'm', true);
        }

        KeyController::createBind('X', [self::class, 'reload']);
    }

    public static function add(Player $player, $message, $pause)
    {
        $id = InfoMessage::insertGetId([
            'text'  => $message,
            'delay' => $pause,
        ]);

        self::reload($player);

        Timer::create('info_message_' . $id, function () use ($message) {
            ChatController::message(onlinePlayers(), '_info', $message->text);
        }, $message->delay . 'm', true);
    }

    public static function update(Player $player, $id, $message, $pause)
    {
        InfoMessage::whereId($id)->update([
            'text'  => $message,
            'delay' => $pause,
        ]);

        self::reload($player);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        $messages = InfoMessage::all();
        Template::show($player, 'info-messages.manialink', compact('messages'));
    }
}
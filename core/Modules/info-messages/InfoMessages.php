<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Interfaces\ModuleInterface;
use esc\Models\AccessRight;
use esc\Models\InfoMessage;
use esc\Models\Player;

class InfoMessages implements ModuleInterface
{
    private static $startTime;

    public function __construct()
    {
        self::$startTime = time();
    }

    private static function minutesSinceStart()
    {
        return (time() - self::$startTime) / 60;
    }

    public static function displayInfoMessages()
    {
        $minutesSinceStart = self::minutesSinceStart();
        $messages          = InfoMessage::all();

        foreach ($messages as $message) {
            if ($minutesSinceStart % $message->delay == 0) {
                infoMessage($message->text)->sendAll();
            }
        }
    }

    public static function add(Player $player, $pause, ...$messageParts)
    {
        $message = implode(',', $messageParts);

        InfoMessage::create([
            'text'  => $message,
            'delay' => $pause,
        ]);

        self::showSettings($player);
    }

    public static function update(Player $player, $id, $pause, ...$messageParts)
    {
        $message = implode(',', $messageParts);

        InfoMessage::whereId($id)->update([
            'text'  => $message,
            'delay' => $pause,
        ]);

        self::showSettings($player);
    }

    public static function delete(Player $player, $id)
    {
        InfoMessage::whereId($id)->delete();

        self::showSettings($player);
    }

    public static function showSettings(Player $player)
    {
        $messages = InfoMessage::all();
        Template::show($player, 'info-messages.manialink', compact('messages'));
    }

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function start(string $mode)
    {

        AccessRight::createIfMissing('info_messages', 'Add/edit/remove reccuring info-messages.');

        ChatCommand::add('//messages', [InfoMessages::class, 'showSettings'], 'Set up recurring server messages', 'info_messages');

        ManiaLinkEvent::add('info.add', [self::class, 'add'], 'info_messages');
        ManiaLinkEvent::add('info.update', [self::class, 'update'], 'info_messages');
        ManiaLinkEvent::add('info.delete', [self::class, 'delete'], 'info_messages');

        Timer::create('display_info_messages', [self::class, 'displayInfoMessages'], '1m', true);
    }
}
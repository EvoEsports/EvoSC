<?php

namespace esc\Modules;


use esc\Classes\ChatCommand;
use esc\Classes\DB;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Interfaces\ModuleInterface;
use esc\Models\AccessRight;
use esc\Models\InfoMessage;
use esc\Models\Player;
use Illuminate\Support\Collection;

class InfoMessages implements ModuleInterface
{
    public static function displayInfoMessages()
    {
        $messages = DB::table('info-messages')->select('text')->whereRaw('UNIX_TIMESTAMP() % delay = 0')->get();
        foreach ($messages as $message) {
            infoMessage($message->text)->sendAll();
        }
    }

    public static function update(Player $player, Collection $formData)
    {
        $interval = $formData->interval;

        if ($interval < 1) {
            $interval = 1;
        }

        InfoMessage::updateOrCreate([
            'id' => $formData->id
        ], [
            'text' => $formData->message,
            'delay' => $interval,
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

    public static function showCreate(Player $player, $id = null)
    {
        $message = '';
        $interval = '';

        if ($id) {
            $infoMessage = InfoMessage::find($id);
            $message = $infoMessage->text;
            $interval = $infoMessage->delay;
        }

        Template::show($player, 'info-messages.edit', compact('id', 'message', 'interval'));
    }

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {

        AccessRight::createIfMissing('info_messages', 'Add/edit/remove reccuring info-messages.');

        ChatCommand::add('//messages', [InfoMessages::class, 'showSettings'], 'Set up recurring server messages', 'info_messages');

        ManiaLinkEvent::add('info.show', [self::class, 'showSettings'], 'info_messages');
        ManiaLinkEvent::add('info.update', [self::class, 'update'], 'info_messages');
        ManiaLinkEvent::add('info.delete', [self::class, 'delete'], 'info_messages');
        ManiaLinkEvent::add('info.show_create', [self::class, 'showCreate'], 'info_messages');

        Timer::create('display_info_messages', [self::class, 'displayInfoMessages'], '1m', true);
    }
}
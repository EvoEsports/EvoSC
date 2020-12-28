<?php

namespace EvoSC\Modules\InfoMessages;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use EvoSC\Modules\InfoMessages\Models\InfoMessage;

class InfoMessages extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        AccessRight::add('info_messages', 'Add/edit/remove reccuring info-messages.');

        ManiaLinkEvent::add('info.update', [self::class, 'update'], 'info_messages');
        ManiaLinkEvent::add('info.delete', [self::class, 'delete'], 'info_messages');
        ManiaLinkEvent::add('info.show_create', [self::class, 'showCreate'], 'info_messages');
        ManiaLinkEvent::add('info.show', [self::class, 'showSettings'], 'info_messages')
            ->withScoreTableButton('', 'Info Messages');

        Timer::create('display_info_messages', [self::class, 'displayInfoMessages'], '1m', true);

        ChatCommand::add('//messages', [InfoMessages::class, 'showSettings'], 'Set up recurring server messages', 'info_messages');
    }

    public static function displayInfoMessages()
    {
        $messages = DB::table('info-messages')->select('text')->whereRaw('ROUND(UNIX_TIMESTAMP()/60) % delay = 0')->get();
        foreach ($messages as $message) {
            infoMessage($message->text)->sendAll();
        }
    }

    public static function update(Player $player, $formData)
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
        $messages = InfoMessage::all()->values();
        $count = $messages->count();
        $messages = $messages->toJson();
        Template::show($player, 'InfoMessages.manialink', compact('messages', 'count'));
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

        Template::show($player, 'InfoMessages.edit', compact('id', 'message', 'interval'));
    }
}
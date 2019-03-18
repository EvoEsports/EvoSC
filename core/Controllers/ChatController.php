<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\Classes\ChatMessage;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Module;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Dedi;
use esc\Models\Group;
use esc\Models\LocalRecord;
use esc\Models\Map;
use esc\Models\Player;
use esc\Models\Song;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\FaultException;

class ChatController implements ControllerInterface
{
    /**
     * @var Collection
     */
    private static $mutedPlayers;

    public static function init()
    {
        self::$mutedPlayers = collect();

        try {
            Server::call('ChatEnableManualRouting', [true, false]);
        } catch (FaultException $e) {
            $msg = $e->getMessage();
            Log::getOutput()->writeln("<error>$msg There might already be a running instance of EvoSC.</error>");
            exit(2);
        }

        Hook::add('PlayerChat', [self::class, 'playerChat']);

        AccessRight::createIfNonExistent('player_mute', 'Mute/unmute player.');
        AccessRight::createIfNonExistent('admin_echoes', 'Receive admin messages.');

        ChatCommand::add('//mute', [self::class, 'mute'], 'Mutes a player by given nickname', 'player_mute');
        ChatCommand::add('//unmute', [self::class, 'unmute'], 'Unmute a player by given nickname', 'player_mute');
        ChatCommand::add('/pm', [self::class, 'pm'], 'Send a private message. Usage: /pm <partial_nick> message...');
    }

    public static function mute(Player $player, $cmd, $nick)
    {
        $target = PlayerController::findPlayerByName($player, $nick);

        if (!$target) {
            //No target found
            return;
        }

        $ply     = collect();
        $ply->id = $target->id;

        self::$mutedPlayers = self::$mutedPlayers->push($ply)->unique();
    }

    public static function unmute(Player $player, $cmd, $nick)
    {
        $target = PlayerController::findPlayerByName($player, $nick);

        if (!$target) {
            //No target found
            return;
        }

        self::$mutedPlayers = self::$mutedPlayers->filter(function ($player) use ($target) {
            return $player->id != $target->id;
        });
    }

    public static function pm(Player $player, $cmd, $nick, ...$message)
    {
        $target = PlayerController::findPlayerByName($player, $nick);

        if (!$target) {
            //No target found
            return;
        }

        if ($target == $player) {
            warningMessage('Why are you talking to yourself? Do you need help?')->send($player);

            return;
        }

        $prefix = sprintf(secondary('[PM->') . $player . secondary('] '));
        $pm     = \chatMessage($prefix . implode(' ', $message))->setIcon('');

        $pm->send($player);
        $pm->send($target);
    }

    public
    static function playerChat(Player $player, $text)
    {
        $parts = explode(' ', $text);

        if (ChatCommand::has($parts[0])) {
            ChatCommand::get($parts[0])->execute($player, $text);

            return;
        }

        if (self::$mutedPlayers->where('id', $player->id)->isNotEmpty()) {
            //Player is muted
            warningMessage('You are muted.')->send($player);

            return;
        }

        Log::logAddLine("Chat", '[' . $player . '] ' . $text, true);
        $nick = $player->NickName;

        if (preg_match('/([$]+)$/', $text, $matches)) {
            //Escape dollar signs
            $text .= $matches[0];
        }

        if ($player->isSpectator()) {
            $nick = '$eee📷 ' . $nick;
        }

        $prefix   = $player->group->chat_prefix;
        $color    = $player->group->color ?? config('colors.chat');
        $chatText = sprintf('$%s[$z$s%s$z$s$%s] $%s$z$s%s', $color, $nick, $color, config('colors.chat'), $text);

        if ($prefix) {
            $chatText = '$' . $color . $prefix . ' ' . $chatText;
        }

        Server::call('ChatSendServerMessage', [$chatText]);
    }
}
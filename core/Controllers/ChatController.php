<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Player;
use Illuminate\Support\Collection;
use Maniaplanet\DedicatedServer\Xmlrpc\FaultException;

/**
 * Class ChatController
 *
 * Handle chat-messages and commands.
 *
 * @package esc\Controllers
 */
class ChatController implements ControllerInterface
{
    /**
     * @var Collection
     */
    private static $mutedPlayers;

    private static $routingEnabled;

    /**
     * Initialize ChatController.
     */
    public static function init()
    {
        self::$mutedPlayers   = collect();
        self::$routingEnabled = config('server.enable-chat-routing') ?? true;

        if (self::$routingEnabled) {
            Log::logAddLine('ChatController', 'Enabling manual chat routing.');

            try {
                Server::call('ChatEnableManualRouting', [true, false]);
            } catch (FaultException $e) {
                $msg = $e->getMessage();
                Log::getOutput()->writeln("<error>$msg There might already be a running instance of EvoSC.</error>");
                exit(2);
            }

            Hook::add('PlayerChat', [self::class, 'playerChat']);
        } else {
            Server::call('ChatEnableManualRouting', [false, false]);
        }

        AccessRight::createIfNonExistent('player_mute', 'Mute/unmute player.');
        AccessRight::createIfNonExistent('admin_echoes', 'Receive admin messages.');

        ChatCommand::add('//mute', [self::class, 'muteCmd'], 'Mutes a player by given nickname', 'player_mute');
        ChatCommand::add('//unmute', [self::class, 'unmute'], 'Unmute a player by given nickname', 'player_mute');
        ChatCommand::add('/pm', [self::class, 'pm'], 'Send a private message. Usage: /pm <partial_nick> message...');
    }

    /**
     * Chat-command: mute player.
     *
     * @param \esc\Models\Player $admin
     * @param                    $cmd
     * @param                    $nick
     */
    public static function muteCmd(Player $admin, $cmd, $nick)
    {
        $target = PlayerController::findPlayerByName($admin, $nick);

        if (!$target) {
            //No target found
            return;
        }

        self::mute($admin, $target);
    }

    /**
     * Mute a player
     *
     * @param \esc\Models\Player $admin
     * @param \esc\Models\Player $target
     */
    public static function mute(Player $admin, Player $target)
    {
        Server::ignore($target->Login);
        infoMessage($admin, ' muted ', $target)->sendAll();
    }

    /**
     * Chat-command: unmute player.
     *
     * @param \esc\Models\Player $player
     * @param                    $cmd
     * @param                    $nick
     */
    public static function unmute(Player $player, $cmd, $nick)
    {
        $target = PlayerController::findPlayerByName($player, $nick);

        if (!$target) {
            //No target found
            return;
        }

        Server::unIgnore($target->Login);
        infoMessage($player, ' unmuted ', $target)->sendAll();
    }

    /**
     * Chat-command: send pm to a player
     *
     * @param \esc\Models\Player $player
     * @param                    $cmd
     * @param                    $nick
     * @param mixed              ...$message
     */
    public static function pm(Player $player, $cmd, $nick, ...$message)
    {
        $target = PlayerController::findPlayerByName($player, $nick);

        if (!$target) {
            //No target found
            return;
        }

        if ($target->id == $player->id) {
            warningMessage('You can\'t PM yourself.')->send($player);

            return;
        }

        $from = sprintf(secondary('[from:') . $player . secondary('] '));
        $to   = sprintf(secondary('[to:') . $target . secondary('] '));

        chatMessage($from . implode(' ', $message))->setIcon('')->send($target);
        chatMessage($to . implode(' ', $message))->setIcon('')->send($player);
    }

    /**
     * Process chat-message and detect commands.
     *
     * @param \esc\Models\Player $player
     * @param                    $text
     */
    public static function playerChat(Player $player, $text)
    {
        if (substr($text, 0, 1) == '/' || substr($text, 0, 2) == '/') {
            warningMessage('Invalid chat-command entered. See ', secondary('/help'), ' for all commands.')->send($player);
            warningMessage('We switched to a new server-controller, it is missing features you had before but we are working on it to give you the best user-experience.')->send($player);

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

    /**
     * @return mixed
     */
    public static function getRoutingEnabled()
    {
        return self::$routingEnabled;
    }
}
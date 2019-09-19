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

    /** @var boolean */
    private static $routingEnabled;

    /**
     * Initialize ChatController.
     */
    public static function init()
    {
        self::$mutedPlayers = collect();
        /*
        self::$routingEnabled = config('server.enable-chat-routing');

        if (self::$routingEnabled) {
            Log::write('Enabling manual chat routing.');
            $routingEnabled = false;

            while (!$routingEnabled){
                try {
                    Server::chatEnableManualRouting(false, false);
                    $routingEnabled = true;
                } catch (FaultException $e) {
                    $msg = $e->getMessage();
                    Log::getOutput()->writeln("<error>$msg There might already be a running instance of EvoSC.</error>");
                    sleep(1);
                }
            }
        } else {
            Server::chatEnableManualRouting(false, false);
        }
        */

        AccessRight::createIfMissing('player_mute', 'Mute/unmute player.');
        AccessRight::createIfMissing('admin_echoes', 'Receive admin messages.');
    }

    /**
     * Mute a player
     *
     * @param  Player  $admin
     * @param  Player  $target
     */
    public static function mute(Player $admin, Player $target)
    {
        if (!self::isPlayerMuted($target)) {
            Server::ignore($target->Login);
        }
        infoMessage($admin, ' muted ', $target)->sendAll();
    }

    /**
     * Unmute a player
     *
     * @param  Player  $player
     * @param  Player  $target
     */
    public static function unmute(Player $player, Player $target)
    {
        if (!self::isPlayerMuted($target)) {
            Server::unIgnore($target->Login);
        }
        infoMessage($player, ' unmuted ', $target)->sendAll();
    }

    /**
     * Chat-command: mute player.
     *
     * @param  Player  $admin
     * @param                    $cmd
     * @param                    $nick
     */
    public static function cmdMute(Player $admin, $cmd, $nick)
    {
        $target = PlayerController::findPlayerByName($admin, $nick);

        if (!$target) {
            //No target found
            return;
        }

        self::mute($admin, $target);
    }

    /**
     * Chat-command: unmute player.
     *
     * @param  Player  $player
     * @param                    $cmd
     * @param                    $nick
     */
    public static function cmdUnmute(Player $player, $cmd, $nick)
    {
        $target = PlayerController::findPlayerByName($player, $nick);

        if (!$target) {
            //No target found
            return;
        }

        Server::unIgnore($target->Login);
        infoMessage($player, ' unmuted ', $target)->sendAll();
    }

    public static function isPlayerMuted(Player $player)
    {
        return collect(Server::getIgnoreList())->contains('login', '=', $player->Login);
    }

    /**
     * Chat-command: send pm to a player
     *
     * @param  Player  $player
     * @param  string  $cmd
     * @param  string  $nick
     * @param  mixed  ...$message
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

        $from = sprintf(secondary('[from:').$player.secondary('] '));
        $to = sprintf(secondary('[to:').$target.secondary('] '));

        chatMessage($from.implode(' ', $message))->setIcon('')->send($target);
        chatMessage($to.implode(' ', $message))->setIcon('')->send($player);
    }

    /**
     * Process chat-message and detect commands.
     *
     * @param  Player  $player
     * @param  string  $text
     */
    public static function playerChat(Player $player, $text)
    {
        if (substr($text, 0, 1) == '/' || substr($text, 0, 2) == '/') {
            warningMessage('Invalid chat-command entered. See ', secondary('/help'), ' for all commands.')->send($player);

            return;
        }

        if (self::$mutedPlayers->where('id', $player->id)->isNotEmpty()) {
            //Player is muted
            warningMessage('You are muted.')->send($player);

            return;
        }

        Log::write('<fg=yellow>['.$player.'] '.$text.'</>', true);
    }

    /**
     * @return mixed
     */
    public static function getRoutingEnabled()
    {
        return self::$routingEnabled;
    }

    /**
     * @param  string  $mode
     * @param  bool  $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        ChatCommand::add('//mute', [self::class, 'cmdMute'], 'Mutes a player by given nickname', 'player_mute');
        ChatCommand::add('//unmute', [self::class, 'cmdUnmute'], 'Unmute a player by given nickname', 'player_mute');
        ChatCommand::add('/pm', [self::class, 'pm'], 'Send a private message. Usage: /pm <partial_nick> message...');
    }
}
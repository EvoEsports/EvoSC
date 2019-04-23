<?php

namespace esc\Classes;


use esc\Models\Map;
use esc\Models\Player;

/**
 * Class ChatMessage
 *
 * Create and send chat/info/warning messages.
 *
 * @package esc\Classes
 */
class ChatMessage
{
    private $parts;
    private $color;
    private $icon;

    /**
     * ChatMessage constructor.
     *
     * Parts can be strings, numbers, player/group/map/local/etc-objects (most objects are formatted automatically).
     *
     * @param mixed ...$message
     */
    public function __construct(...$message)
    {
        $this->color = config('colors.primary');
        $this->parts = $message;
    }

    /**
     * Set the primary color of the chat message.
     *
     * @param string $color
     *
     * @return \esc\Classes\ChatMessage
     */
    public function setColor(string $color): ChatMessage
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Set the icon of the chat-message, can contain color-code.
     *
     * @param string $icon
     *
     * @return \esc\Classes\ChatMessage
     */
    public function setIcon(string $icon): ChatMessage
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set info-color and icon on chat-message.
     *
     * @return \esc\Classes\ChatMessage
     */
    public function setIsInfoMessage(): ChatMessage
    {
        $this->color = config('colors.info');
        $this->icon  = '';

        return $this;
    }

    /**
     * Set warning-color and icon on chat-message.
     *
     * @return \esc\Classes\ChatMessage
     */
    public function setIsWarning(): ChatMessage
    {
        $this->color = config('colors.warning');
        $this->icon  = '';

        return $this;
    }

    /**
     * Overwrite the chat-message content.
     *
     * @param mixed ...$parts
     *
     * @return \esc\Classes\ChatMessage
     */
    public function setParts(...$parts): ChatMessage
    {
        $this->parts = $parts;

        return $this;
    }

    /**
     * Get the formatted message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        $message = '';

        foreach ($this->parts as $part) {
            if ($part instanceof Player || $part instanceof Map) {
                $message .= secondary($part) . '$z$s';
                continue;
            }

            if (is_numeric($part) || preg_match('/(\d:)?\d{2}\.\d{3}/', $part)) {
                $message .= secondary($part);
                continue;
            }

            $message .= '$z$s$' . $this->color . $part;
        }

        if ($this->icon) {
            return '$fff' . $this->icon . ' $z$s' . $message;
        }

        return $message;
    }

    /**
     * Send the message to everyone.
     */
    public function sendAll()
    {
        Server::chatSendServerMessage($this->getMessage());
    }

    /**
     * Send the message to everyone with the admin_echoes access-right.
     */
    public function sendAdmin()
    {
        $this->setIcon('$666');
        $message = $this->getMessage();

        echoPlayers()->each(function (Player $player) use ($message) {
            Server::chatSendServerMessage($message, $player->Login, true);
        });

        Server::executeMulticall();
    }

    /**
     * Send the message to a specific player.
     *
     * @param \esc\Models\Player|null|string $player
     */
    public function send($player = null)
    {
        if (!$player) {
            return;
        }

        if ($player instanceof Player) {
            Server::chatSendServerMessage($this->getMessage(), $player->Login);
        } else {
            if (is_string($player)) {
                Server::chatSendServerMessage($this->getMessage(), $player);
            }
        }
    }
}
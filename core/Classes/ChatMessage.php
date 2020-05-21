<?php

namespace EvoSC\Classes;


use EvoSC\Models\Map;
use EvoSC\Models\Player;
use stdClass;

/**
 * Class ChatMessage
 *
 * Create and send chat/info/warning messages.
 *
 * @package EvoSC\Classes
 */
class ChatMessage
{
    private array $parts;
    private $color;
    private $icon;

    /**
     * ChatMessage constructor.
     *
     * Parts can be strings, numbers, player/group/map/local/etc-objects (most objects are formatted automatically).
     *
     * @param  mixed  ...$message
     */
    public function __construct(...$message)
    {
        $this->color = config('theme.chat.info');
        $this->parts = $message;
    }

    /**
     * Set the primary color of the chat message.
     *
     * @param  string  $color
     *
     * @return ChatMessage
     */
    public function setColor(string $color): ChatMessage
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Set the icon of the chat-message, can contain color-code.
     *
     * @param  string  $icon
     *
     * @return ChatMessage
     */
    public function setIcon(string $icon): ChatMessage
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set info-color and icon on chat-message.
     *
     * @return ChatMessage
     */
    public function setIsInfoMessage(): ChatMessage
    {
        $this->color = config('theme.chat.info');
        $this->icon = '';

        return $this;
    }

    /**
     * Set warning-color and icon on chat-message.
     *
     * @return ChatMessage
     */
    public function setIsWarning(): ChatMessage
    {
        $this->color = config('theme.chat.warning');
        $this->icon = '';

        return $this;
    }

    /**
     * @return $this
     */
    public function setIsDanger(): ChatMessage
    {
        $this->color = config('theme.chat.danger');
        $this->icon = '';

        return $this;
    }

    /**
     * @return $this
     */
    public function setIsSuccess(): ChatMessage
    {
        $this->color = config('theme.chat.success');
        $this->icon = '';

        return $this;
    }

    /**
     * Overwrite the chat-message content.
     *
     * @param  mixed  ...$parts
     *
     * @return ChatMessage
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
                $message .= secondary($part).'$z$s';
                continue;
            }

            if ($part instanceof stdClass) {
                var_dump($part);
                continue;
            }

            if (is_numeric($part) || preg_match('/(\d:)?\d{2}\.\d{3}/', "$part")) {
                $message .= secondary("$part");
                continue;
            }

            $message .= '$z$s$'.$this->color.$part;
        }

        if ($this->icon) {
            return '$fff'.$this->icon.' $z$s'.$message;
        }

        if (substr($message, -1) != '.') {
            $message .= '$z$s$'.$this->color.'.';
        }

        return $message;
    }

    /**
     * Send the message to everyone.
     */
    public function sendAll()
    {
        Server::chatSendServerMessage($this->getMessage());
        Log::info($this->getMessage());
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
        Log::info($this->getMessage());
    }

    /**
     * Send the message to a specific player.
     *
     * @param Player|null|string  $player
     */
    public function send($player = null)
    {
        if (!$player) {
            return;
        }

        if ($player instanceof Player) {
            Server::chatSendServerMessage($this->getMessage(), $player->Login);
            Log::info("(@$player)".$this->getMessage(), isVerbose());
        } else {
            if (is_string($player)) {
                Server::chatSendServerMessage($this->getMessage(), $player);
                Log::info("(@$player)".$this->getMessage(), isVerbose());
            }
        }
    }
}
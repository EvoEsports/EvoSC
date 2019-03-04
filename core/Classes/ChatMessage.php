<?php

namespace esc\Classes;


use esc\Models\Player;

class ChatMessage
{
    private $parts;
    private $color;
    private $icon;
    private $isInfoMessage = false;
    private $isWarning     = false;

    public function __construct(...$message)
    {
        $this->color = config('colors.primary');
        $this->parts = $message;
    }

    public function setColor(string $color): ChatMessage
    {
        $this->color = $color;

        return $this;
    }

    public function setIcon(string $icon): ChatMessage
    {
        $this->icon = $icon;

        return $this;
    }

    public function setIsInfoMessage(): ChatMessage
    {
        $this->isInfoMessage = true;

        return $this;
    }

    public function setIsWarning(): ChatMessage
    {
        $this->isWarning = true;

        return $this;
    }

    public function sendAll()
    {
        Server::chatSendServerMessage($this->getMessage());
    }

    public function send(Player $player)
    {
        Server::chatSendServerMessage($this->getMessage(), $player->Login);
    }

    public function setParts(...$parts)
    {
        $this->parts = $parts;
    }

    public function getMessage(): string
    {
        if ($this->isInfoMessage) {
            $this->color = config('colors.info');
        }

        if ($this->isWarning) {
            $this->color = config('colors.warning');
        }

        $message = '';

        foreach ($this->parts as $part) {
            if (is_numeric($part)) {
                $message .= secondary($part);
                continue;
            }

            if (is_string($part)) {
                $message .= '$' . $this->color . $part;
                continue;
            }

            $message .= $part;
        }

        if ($this->icon) {
            return '$fff' . $this->icon . ' ' . $message;
        }

        return $message;
    }
}
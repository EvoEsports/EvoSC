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
        $this->color         = config('colors.info');
        $this->icon          = '';

        return $this;
    }

    public function setIsWarning(): ChatMessage
    {
        $this->isWarning = true;
        $this->color     = config('colors.warning');
        $this->icon      = '';

        return $this;
    }

    public function setParts(...$parts): ChatMessage
    {
        $this->parts = $parts;

        return $this;
    }

    public function getMessage(): string
    {
        $message = '';

        foreach ($this->parts as $part) {
            if (is_numeric($part) || preg_match('/(\d:)?\d{2}\.\d{3}/', $part)) {
                $message .= secondary($part);
                continue;
            }

            if (is_string($part)) {
                $message .= '$' . $this->color . $part;
                continue;
            }

            if ($part instanceof Player) {
                $message .= $part . '$z$s';
                continue;
            }

            $message .= '$z$s' . $part;
        }

        if ($this->icon) {
            return '$fff' . $this->icon . ' $z$s' . $message;
        }

        return $message;
    }

    public function sendAll()
    {
        Server::chatSendServerMessage($this->getMessage());
    }

    public function send(Player $player)
    {
        Server::chatSendServerMessage($this->getMessage(), $player->Login);
    }
}
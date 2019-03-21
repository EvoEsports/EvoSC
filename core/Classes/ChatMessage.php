<?php

namespace esc\Classes;


use esc\Models\Player;

class ChatMessage
{
    private $parts;
    private $color;
    private $icon;

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
        $this->color = config('colors.info');
        $this->icon  = '';

        return $this;
    }

    public function setIsWarning(): ChatMessage
    {
        $this->color = config('colors.warning');
        $this->icon  = '';

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

            if ($part instanceof Player) {
                $message .= secondary($part) . '$z$s';
                continue;
            }

            $message .= '$z$s$' . $this->color . $part;
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

    public function sendAdmin()
    {
        $this->setIcon('$666');
        $message = $this->getMessage();

        echoPlayers()->each(function (Player $player) use ($message) {
            Server::chatSendServerMessage($message, $player->Login, true);
        });

        Server::executeMulticall();
    }

    public function send(Player $player = null)
    {
        if (!$player) {
            return;
        }

        Server::chatSendServerMessage($this->getMessage(), $player->Login);
    }
}
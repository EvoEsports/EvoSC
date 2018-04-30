<?php

namespace esc\Classes;


use esc\Controllers\TemplateController;
use esc\Models\Player;

class Template
{
    public $index;
    public $template;

    public function __construct(string $index, string $template)
    {
        $this->index = $index;
        $this->template = $template;
    }

    public static function showAll(string $index, array $values = null)
    {
        if (!$values) {
            $values = [];
        }

        $xml = TemplateController::getTemplate($index, $values);
        Server::sendDisplayManialinkPage('', $xml);
    }

    public static function hideAll(string $index)
    {
        self::showAll('blank', [
            'id' => $index
        ]);
    }

    public static function show(Player $player, string $index, array $values = null)
    {
        if (!$values) {
            $values = [];
        }

        $xml = TemplateController::getTemplate($index, $values);
        Server::sendDisplayManialinkPage($player->Login, $xml);
    }

    public static function toString(string $index, array $values = null): string
    {
        if (!$values) {
            $values = [];
        }

        return TemplateController::getTemplate($index, $values);
    }

    public static function hide(Player $player, string $index)
    {
        self::show($player, 'blank', [
            'id' => $index
        ]);
    }
}
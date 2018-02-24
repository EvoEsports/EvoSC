<?php

namespace esc\Classes;


use esc\controllers\TemplateController;
use esc\models\Player;

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
        Server::getRpc()->sendDisplayManialinkPage('', $xml);
    }

    public static function hideAll(string $index)
    {
        $xml = TemplateController::getBlankTemplate($index);
        Server::getRpc()->sendDisplayManialinkPage('', $xml);
    }

    public static function show(Player $player, string $index, array $values = null)
    {
        if (!$values) {
            $values = [];
        }

        $xml = TemplateController::getTemplate($index, $values);
        Server::getRpc()->sendDisplayManialinkPage($player->Login, $xml);
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
        $xml = TemplateController::getBlankTemplate($index);
        Server::getRpc()->sendDisplayManialinkPage($player->Login, $xml);
    }

    public static function add(string $index, string $template = null)
    {
        if (!$template) {
            Log::error("Could not load template: $index");
            return;
        }

        TemplateController::addTemplate($index, $template);
    }
}
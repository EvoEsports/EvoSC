<?php

namespace esc\classes;


use esc\controllers\ServerController;
use esc\controllers\TemplateController;

class Template
{
    public $index;
    public $template;

    public function __construct(string $index, string $template)
    {
        $this->index = $index;
        $this->template = $template;
    }

    public static function sendToAll(string $index, array $values)
    {
        $xml = TemplateController::getTemplate($index, $values);
        ServerController::getRpc()->sendDisplayManialinkPage('', $xml);
    }

    public static function add(string $index, string $template)
    {
        TemplateController::addTemplate($index, $template);
    }
}
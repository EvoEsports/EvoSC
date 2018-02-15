<?php

namespace esc\classes;


use esc\controllers\RpcController;
use esc\controllers\TemplateController;
use Philo\Blade\Blade;

class Template
{
    public $index;
    public $template;

    public function __construct(string $index, string $template)
    {
        $this->index = $index;
        $this->template = $template;
    }

    public function fill(...$values)
    {
        $blade = new Blade();
    }

    public function sendToAll()
    {
        RpcController::call('SendDisplayManialinkPage', [$this->template, 0, false]);
    }

    public static function add(string $index, string $template)
    {
        TemplateController::addTemplate($index, $template);
    }

    public static function get(string $index): ?Template
    {
        return TemplateController::getTemplate($index);
    }
}
<?php

namespace esc\controllers;


use esc\classes\Template;
use Illuminate\Support\Collection;

class TemplateController
{
    private static $templates;

    public static function init()
    {
        self::$templates = new Collection();
    }

    private static function templates(): Collection
    {
        return self::$templates;
    }

    public static function addTemplate(string $index, string $templateString)
    {
        $template = new Template($index, $templateString);
        self::templates()->push($template);
    }

    public static function getTemplate(string $index): ?Template
    {
        return self::templates()->where('index', $index)->first();
    }
}
<?php

namespace esc\Controllers;


use esc\Classes\Timer;
use Illuminate\Support\Collection;
use Latte\Engine;
use Latte\Loaders\StringLoader;

class TemplateController
{
    private static $latte;
    private static $templates;

    private static function getTemplates(): Collection
    {
        return self::getTemplates();
    }

    public static function init()
    {
        self::$templates = new Collection();
        self::$latte = new Engine();

//        Timer::create('template.reload', 'esc\Controllers\TemplateController::checkTemplateChanges', '2s');
    }

    public static function addTemplate(string $index, string $templateString)
    {
        self::$templates->put($index, $templateString);
        self::$latte->setLoader(new StringLoader(self::$templates->toArray()));
    }

    public static function getTemplate(string $index, $values): string
    {
        return self::$latte->renderToString($index, $values);
    }

    public static function getBlankTemplate(string $index): string
    {
        return substr(self::$templates[$index], 0, strpos(self::$templates[$index], '<frame')) . '</manialink>';
    }

    public static function checkTemplateChanges()
    {
        //TODO: Automaticly reload templates

        //Timer::create('template.reload', 'esc\Controllers\TemplateController::checkTemplateChanges', '2s');
    }
}
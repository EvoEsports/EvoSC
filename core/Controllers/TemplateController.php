<?php

namespace esc\Controllers;


use esc\Classes\File;
use esc\Classes\Log;
use Illuminate\Support\Collection;
use Latte\Engine;
use Latte\Loaders\StringLoader;

class TemplateController
{
    private static $latte;
    private static $templates;

    public static function init()
    {
        Log::logAddLine('TemplateController', 'Starting...');

        self::$templates = collect();
        self::$latte = new Engine();
        self::loadTemplates();
    }

    private static function getTemplates(): Collection
    {
        return self::getTemplates();
    }

    public static function addTemplate(string $index, string $templateString)
    {
    }

    public static function getTemplate(string $index, $values): string
    {
        return self::$latte->renderToString($index, $values);
    }

    public static function getBlankTemplate(string $index): string
    {
        return substr(self::$templates[$index], 0, strpos(self::$templates[$index], '<frame')) . '</manialink>';
    }

    public static function loadTemplates()
    {
        Log::logAddLine('TemplateController', 'Loading templates...');

        //Get all template files in core directory
        $templates = File::getFilesRecursively(coreDir(), '/\.latte\.xml$/')
            ->map(function (&$template) {
                $templateObject = collect();

                //Get path relative to core directory
                $relativePath = str_replace(coreDir('/'), '', $template);

                //Generate template id from filename & path
                $templateObject->id = self::getTemplateId($relativePath, $templateObject);

                //Load template contents
                $templateObject->template = file_get_contents($template);

                //Assign as new value
                return $templateObject;
            });

        self::$templates = $templates;

        File::put(cacheDir('templates.json'), $templates->pluck('template', 'id')->toJson());

        //Set id <=> template map as loader for latte
        $templateMap = $templates->pluck('template', 'id')->toArray();
        self::$latte->setLoader(new StringLoader($templateMap));
    }

    private static function getTemplateId($relativePath, $templateObject)
    {
        $id = '';

        //Split directory structure
        $pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);

        //Remove first entry
        $location = array_shift($pathParts);

        if ($location == 'Modules') {
            array_splice($pathParts, 1, 1);
        }

        //Remove last entry
        $filename = array_pop($pathParts);

        if (count($pathParts) > 0) {
            //Template is in sub-directory
            $id .= implode('.', $pathParts) . '.';
        }

        $id .= str_replace('.latte.xml', '', $filename);

        return strtolower($id);
    }
}
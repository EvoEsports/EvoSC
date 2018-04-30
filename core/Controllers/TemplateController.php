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
        self::$templates = File::getFilesRecursively(coreDir(), '/(\.latte\.xml|\.script\.txt)$/')
            ->map(function (&$template) {
                $templateObject = collect();

                //Get path relative to core directory
                $relativePath = str_replace(coreDir('/'), '', $template);

                //Generate template id from filename & path
                $templateObject->id = self::getTemplateId($relativePath);

                //Load template contents
                $templateObject->template = file_get_contents($template);

                //Assign as new value
                return $templateObject;
            });

        //Set id <=> template map as loader for latte
        $templateMap = self::$templates->pluck('template', 'id')->toArray();
        $stringLoader = new StringLoader($templateMap);
        self::$latte->setLoader($stringLoader);
    }

    private static function getTemplateId($relativePath)
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

        $id .= str_replace('.latte.xml', '', str_replace('.script.txt', '', $filename));

        return strtolower($id);
    }
}
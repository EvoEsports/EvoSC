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

        $templates = File::getFilesRecursively(coreDir(), '/\.latte\.xml$/')
            ->map(function (&$template) {
                $templateObject = collect();

                //Get sub-directories
                $relativePath = str_replace(coreDir(), '', $template);
                $relativePathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
                $templateLocation = array_shift($relativePathParts);
                $templateObject->filename = array_pop($relativePathParts);

                if ($templateLocation == 'Modules') {
                    //Remove 'Templates'
                    array_splice($relativePathParts, 1, 1);
                }

                //Generate template id from filename & path
                self::setTemplateId($relativePathParts, $templateObject);

                //Load template contents
                $templateObject->template = file_get_contents($template);

                //Assign as new value
                return $templateObject;
            });

        self::$templates = $templates;

        //Set id <=> template map as loader for latte
        $templateMap = $templates->pluck('template', 'id')->toArray();
        self::$latte->setLoader(new StringLoader($templateMap));
    }

    private static function setTemplateId($relativePathParts, &$templateObject)
    {
        $id = '';

        if (count($relativePathParts) > 0) {
            //Template is in sub-dir, add relative path separated by dots
            $id .= implode('.', $relativePathParts) . '.';
        }

        //Remove file info in name and add it to the id
        $id .= str_replace('.latte.xml', '', $templateObject->filename);

        $templateObject->id = strtolower($id);
    }
}
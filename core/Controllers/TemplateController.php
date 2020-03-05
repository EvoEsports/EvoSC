<?php

namespace esc\Controllers;


use Carbon\Carbon;
use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Log;
use esc\Interfaces\ControllerInterface;
use Exception;
use Illuminate\Support\Collection;
use Latte\Engine;
use Latte\Loaders\StringLoader;

/**
 * Class TemplateController
 *
 * Handles loading and rendering templates.
 *
 * @package esc\Controllers
 */
class TemplateController implements ControllerInterface
{
    /**
     * @var Engine
     */
    private static Engine $latte;

    /**
     * @var Collection
     */
    private static Collection $templates;

    /**
     * Initialize TemplateController
     */
    public static function init()
    {
        self::loadTemplates();
    }

    //Add template filters
    private static function addCustomFilters()
    {
        self::$latte->addFilter('date', function ($str) {
            $date = new Carbon($str);

            return $date->format('Y-m-d');

        })->addFilter('score', function ($str) {
            return formatScore($str);
        })->addFilter('cfg', function ($str) {
            return config($str);
        })->addFilter('escape_quotes', function ($str) {
            return ml_escape($str);
        });
    }

    /**
     * Get all loaded templates.
     *
     * @return Collection
     */
    public static function getTemplates(): Collection
    {
        return self::$templates;
    }

    /**
     * Render template with values
     *
     * @param string $index
     * @param        $values
     *
     * @return string
     */
    public static function getTemplate(string $index, $values): string
    {
        try {
            if (isVeryVerbose()) {
                Log::write('Rendering Template: ' . $index);
            }

            return self::$latte->renderToString($index, $values);
        } catch (Exception $e) {
            //Build parameter string
            $parameters = [];
            foreach ($values as $key => $value) {
                array_push($parameters,
                    "<options=bold>$key:</> <fg=yellow>" . json_encode($value, JSON_PRETTY_PRINT) . "</>");
            }
            $vals = implode(', ', $parameters);

            Log::write('Failed to render template: ' . $index . " [$vals]");
            var_dump($e->getTraceAsString());
        }

        return '';
    }

    /**
     * Load templates from all modules.
     *
     */
    public static function loadTemplates()
    {
        Log::info('Loading templates...');

        self::$templates = collect();
        self::$latte = new Engine();
        self::addCustomFilters();

        //Get all template files in core directory
        $coreTemplates = File::getFilesRecursively(coreDir(), '/\.latte\.xml$/');
        $extModuleTemplates = File::getFilesRecursively(coreDir('../modules'), '/\.latte\.xml$/');

        self::$templates = $coreTemplates->merge($extModuleTemplates)->map(function (&$template) {
            $templateObject = collect();

            //Get path relative to core directory
            $relativePath = str_replace(coreDir('/'), '', $template);

            //Generate template id from filename & path
            $templateObject->id = self::getTemplateId($relativePath);

            if (preg_match('/core\.{4}modules\.(.+)\.templates(\..+)/', $templateObject->id, $matches)) {
                $templateObject->id = $matches[1] . $matches[2];
            }

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

    //Convert filename to template id
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

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        ChatCommand::add('//reload-templates', [TemplateController::class, 'loadTemplates'], 'Reload templates', 'ma');
    }
}
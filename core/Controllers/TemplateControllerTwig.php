<?php

namespace esc\Controllers;


use Carbon\Carbon;
use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Log;
use esc\Interfaces\ControllerInterface;
use Illuminate\Support\Collection;
use Latte\Engine;
use Latte\Loaders\StringLoader;

/**
 * Class TemplateControllerTwig
 *
 * Handles loading and rendering templates.
 *
 * @package esc\Controllers
 */
class TemplateControllerTwig implements ControllerInterface
{
    /**
     * @var Engine
     */
    private static $latte;

    /**
     * @var Collection
     */
    private static $templates;

    /**
     * Initialize TemplateControllerTwig
     */
    public static function init()
    {
        Log::logAddLine('TemplateControllerTwig', 'Starting...');

        // ChatCommand::add('//reload-templates', [TemplateControllerTwig::class, 'loadTemplates'], 'Reload templates', 'user.ban');

        self::$templates = collect();
        self::$latte     = new Engine();
        self::loadTemplates();
    }

    /**
     * Get all loaded templates.
     *
     * @return \Illuminate\Support\Collection
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
            Log::logAddLine('TemplateControllerTwig', 'Rendering Template: ' . $index, isVerbose());

            return self::$latte->renderToString($index, $values);
        } catch (\Exception $e) {
            //Build parameter string
            $parameters = [];
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    array_push($parameters, "<options=bold>$key:</> <fg=yellow>" . implode(', ', $value) . "</>");
                } else {
                    array_push($parameters, "<options=bold>$key:</> <fg=yellow>$value</>");
                }
            }
            $vals = implode(', ', $parameters);

            Log::logAddLine('Template:' . $index, 'Failed to render template: ' . $index . " [$vals]");
            var_dump($e->getTraceAsString());
        }

        return '';
    }

    /**
     * Load templates from all modules.
     *
     * @param null $args
     */
    public static function loadTemplates($args = null)
    {
        Log::logAddLine('TemplateControllerTwig', 'Loading templates...');

        //Get all template files in core directory
        self::$templates = File::getFilesRecursively(coreDir(), '/\.twig\.xml$/')
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
        $templateMap  = self::$templates->pluck('template', 'id')->toArray();
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

        $id .= str_replace('.twig.xml', '', $filename);

        return strtolower($id);
    }
}
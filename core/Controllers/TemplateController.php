<?php

namespace EvoSC\Controllers;


use Carbon\Carbon;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use EvoSC\Exceptions\TemplateNameInvalidException;
use EvoSC\Interfaces\ControllerInterface;
use Exception;
use Illuminate\Support\Collection;
use Latte\Engine;
use Latte\Loaders\StringLoader;

/**
 * Class TemplateController
 *
 * Handles loading and rendering templates.
 *
 * @package EvoSC\Controllers
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

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        ChatCommand::add('//reload-templates', [TemplateController::class, 'loadTemplates'], 'Reload templates', 'ma');
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
        })->addFilter('ml_escape', function ($str) {
            return ml_escape($str);
        })->addFilter('classpath', function ($str) {
            return str_replace('\\', '\\\\', $str);
        })->addFilter('ms_bool', function (bool $bool) {
            return $bool ? 'True' : 'False';
        })->addFilter('contrast', function ($str) {
            return self::getContrastColor($str);
        });
    }

    /**
     * Get the color for readable text, based on the given background color.
     * https://stackoverflow.com/questions/1331591/given-a-background-color-black-or-white-text/42921358#42921358
     *
     * @param $hexColor
     * @return string
     */
    private static function getContrastColor($hexColor)
    {
        // hexColor RGB
        $R1 = hexdec(substr($hexColor, 1, 2));
        $G1 = hexdec(substr($hexColor, 3, 2));
        $B1 = hexdec(substr($hexColor, 5, 2));

        // Black RGB
        $blackColor = "#000000";
        $R2BlackColor = hexdec(substr($blackColor, 1, 2));
        $G2BlackColor = hexdec(substr($blackColor, 3, 2));
        $B2BlackColor = hexdec(substr($blackColor, 5, 2));

        // Calc contrast ratio
        $L1 = 0.2126 * pow($R1 / 255, 2.2) +
            0.7152 * pow($G1 / 255, 2.2) +
            0.0722 * pow($B1 / 255, 2.2);

        $L2 = 0.2126 * pow($R2BlackColor / 255, 2.2) +
            0.7152 * pow($G2BlackColor / 255, 2.2) +
            0.0722 * pow($B2BlackColor / 255, 2.2);

        $contrastRatio = 0;
        if ($L1 > $L2) {
            $contrastRatio = (int)(($L1 + 0.05) / ($L2 + 0.05));
        } else {
            $contrastRatio = (int)(($L2 + 0.05) / ($L1 + 0.05));
        }

        // If contrast is more than 5, return black color
        if ($contrastRatio > 5) {
            return config('theme.hud.text-dark');
        } else {
            // if not, return white color.
            return config('theme.hud.text-light');
        }
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
            if (isDebug()) {
                Log::write('Rendering Template: ' . $index);
            }

            return self::$latte->renderToString($index, $values);
        } catch (Exception $e) {
            Log::warningWithCause("Failed to render template $index", $e);
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
        $extModuleTemplates = File::getFilesRecursively(modulesDir(), '/\.latte\.xml$/');

        self::$templates = collect([...$coreTemplates, ...$extModuleTemplates])->map(function ($template) {
            $templateObject = collect();

            //Get path relative to core directory
            $relativePath = str_replace(coreDir('/'), '', $template);

            //Get template id from filename & path
            $templateObject->id = self::getTemplateId($relativePath);

            //Load template contents
            $templateObject->template = File::get($template);

            //Assign as new value
            return $templateObject;
        });

        //Set id <=> template map as loader for latte
        $templateMap = self::$templates->pluck('template', 'id')->toArray();
        $stringLoader = new StringLoader($templateMap);
        self::$latte->setLoader($stringLoader);
    }

    /**
     * @param $relativePath
     * @return string
     * @throws TemplateNameInvalidException
     */
    private static function getTemplateId($relativePath): string
    {
        if (preg_match('/([A-Z][a-zA-Z0-9]+)[\/\\\]Templates[\/\\\](.+?)\.latte\.xml$/', $relativePath, $matches)) {
            return sprintf('%s.%s', $matches[1], $matches[2]);
        } else if (preg_match('/TemplateComponents[\/\\\](\w+)[\/\\\](.+?)\.latte\.xml$/', $relativePath, $matches)) {
            return sprintf('%s.%s', $matches[1], $matches[2]);
        } else {
            throw new TemplateNameInvalidException("The template ID for '$relativePath' could not be detected. Please check that your naming is correct.");
        }
    }
}

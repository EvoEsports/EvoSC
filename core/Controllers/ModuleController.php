<?php

namespace EvoSC\Controllers;

use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\Module;
use EvoSC\Interfaces\ControllerInterface;
use Illuminate\Support\Collection;

/**
 * Class ModuleController
 *
 * @package EvoSC\Controllers
 */
class ModuleController implements ControllerInterface
{
    const PATTERN = '/(?:core\/Modules|modules)\/([A-Z][a-zA-Z0-9]+|[a-z0-9]+\/[A-Z][a-zA-Z0-9]+)\/([A-Z][a-zA-Z0-9]+)\.php/';
    const PATTERNW = '/(?:core\\\\Modules|modules)\\\\([A-Z][a-zA-Z0-9]+|[a-z0-9]+\\\\[A-Z][a-zA-Z0-9]+)\\\\([A-Z][a-zA-Z0-9]+)\.php/';

    /**
     * @var Collection
     */
    private static Collection $loadedModules;

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
    }

    /**
     * Initialize ModuleController.
     */
    public static function init()
    {
        self::$loadedModules = new Collection();
    }

    /**
     * Get all loaded modules.
     *
     * @return Collection
     */
    public static function getModules(): Collection
    {
        return self::$loadedModules;
    }

    public static function startModules(string $mode, bool $isBoot)
    {
        //Boot modules
        Log::info('Starting modules...');

        $coreModules = File::getFilesRecursively(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Modules', '/^[A-Z].+\.php$/');
        $allModules = collect([...$coreModules, ...File::getFilesRecursively(modulesDir(), '/^[A-Z].+\.php$/')]);
        $totalStarted = 0;

        $moduleClasses = $allModules
            ->filter(function ($file) {
                if (isWindows()) {
                    return preg_match(self::PATTERNW, $file);
                } else {
                    return preg_match(self::PATTERN, $file);
                }
            })
            ->mapWithKeys(function ($file) {
                if (isWindows()) {
                    if (preg_match(self::PATTERNW, $file, $matches)) {
                        $dir = $matches[1];
                        $classname = $matches[2];
                        return ["EvoSC\\Modules\\" . str_replace('/', '\\', $dir) . "\\$classname" => dirname($file)];
                    }
                } else {
                    if (preg_match(self::PATTERN, $file, $matches)) {
                        $dir = $matches[1];
                        $classname = $matches[2];
                        return ["EvoSC\\Modules\\" . str_replace('/', '\\', $dir) . "\\$classname" => dirname($file)];
                    }
                }
                return null;
            })
            ->filter()
            ->unique()
            ->mapWithKeys(function ($moduleDir, $moduleClass) use ($mode, $isBoot, &$totalStarted) {
                $files = scandir($moduleDir);
                $configId = null;

                foreach ($files as $file) {
                    if (preg_match('/^(.+)\.config\.json$/', $file, $matches)) {
                        $configId = $matches[1];
                    }
                }

                if ($configId == null) {
                    Log::warning('No config for module: ' . $moduleClass, true);
                    return [$moduleClass => null];
                } else {
                    $config = ConfigController::getConfig($configId, true);
                    $enabled = isset($config->enabled) ? $config->enabled : true;
                    if (!is_null($enabled) && $enabled == false) {
                        $className = str_pad(class_basename($moduleClass), 30, '.', STR_PAD_RIGHT);
                        Log::warning("Module: $className <fg=red;options=bold>[Disabled]</>", isVerbose());
                        return [$moduleClass => null];
                    }
                }

                try {
                    $instance = new $moduleClass();
                } catch (\Error $e) {
                    Log::errorWithCause("Failed to create module $moduleClass (not started)", $e);
                    return [$moduleClass => null];
                }

                if (!($instance instanceof Module)) {
                    Log::error("$moduleClass is not a module, but should be (not started).");
                    return [$moduleClass => null];
                }

                /** @var $moduleClass Module */
                $instance->setConfigId($configId);
                $instance->setDirectory($moduleDir);
                $instance->setNamespace($moduleClass);
                $instance->setName(preg_replace('#^.+[\\\]#', '', $moduleClass));

                return [$moduleClass => $instance];
            })
            ->filter()
            ->sortByDesc(function ($instance) {
                /**
                 * @var Module $instance
                 */
                return $instance->getBootPriority();
            })
            ->each(function (Module $instance, $moduleClass) use ($mode, $isBoot, &$totalStarted) {
                $priority = $instance->getBootPriority();
                switch ($priority) {
                    case Module::PRIORITY_HIGHEST:
                        $color = 'red';
                        break;

                    case Module::PRIORITY_HIGH:
                        $color = 'yellow';
                        break;

                    case Module::PRIORITY_LOW:
                        $color = 'cyan';
                        break;

                    case Module::PRIORITY_LOWEST:
                        $color = 'blue';
                        break;

                    default:
                        $color = 'white';
                }

                if (isVerbose()) {
                    $className = str_pad(class_basename($moduleClass), 30, '.', STR_PAD_RIGHT);
                    Log::info("Module: <fg=green;options=bold>$className</> [<fg=yellow;options=bold>Started</>] [Priority: <fg=$color>$priority</>]");
                } else {
                    Log::getOutput()->write("<fg=$color;options=bold>.</>");
                }

                $instance::start($mode, $isBoot);
                $totalStarted++;
            });

        //Boot modules
        echo "\n";
        Log::cyan("Starting modules finished. $totalStarted modules started.");

        self::$loadedModules = $moduleClasses;

        Hook::fire('ModulesStarted');
    }

    /**
     *
     */
    public static function stopModules()
    {
        self::$loadedModules->each(function (Module $module) {
            try {
                $module->stop();
            } catch (\Exception $e) {
                Log::errorWithCause("Failed to stop module", $e);
            }
        });
    }
}

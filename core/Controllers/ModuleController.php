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
            ->map(function ($moduleDir, $moduleClass) use ($mode, $isBoot, &$totalStarted) {
                $files = scandir($moduleDir);
                $configId = null;
                $config = null;

                foreach ($files as $file) {
                    if (preg_match('/^(.+)\.config\.json$/', $file, $matches)) {
                        $configId = $matches[1];
                    }
                }

                if ($configId == null) {
                    Log::warning('No config for module: ' . $moduleClass, true);
                    return null;
                } else {
                    $config = ConfigController::getConfig($configId, true);
                    $enabled = isset($config->enabled) ? $config->enabled : true;
                    if (!is_null($enabled) && $enabled == false) {
                        Log::warning("Module: $moduleClass [Disabled]", isVerbose());
                        return null;
                    }
                }

                Log::info("Starting $moduleClass.", isVerbose());

                try {
                    $instance = new $moduleClass();
                } catch (\Error $e) {
                    Log::errorWithCause("Failed to create module $moduleClass (not started)", $e);
                    return null;
                }

                if (!($instance instanceof Module)) {
                    Log::error("$moduleClass is not a module, but should be (not started).");
                    return null;
                }

                /** @var $moduleClass Module */
                $instance::start($mode, $isBoot);
                $instance->setConfigId($configId);
                $instance->setDirectory($moduleDir);
                $instance->setNamespace($moduleClass);
                $instance->setName(preg_replace('#^.+[\\\]#', '', $moduleClass));

                if (isVerbose()) {
                    Log::info("Module: $moduleClass [Started]");
                } else {
                    Log::getOutput()->write('<fg=cyan;options=bold>.</>');
                }

                $totalStarted++;

                return $instance;
            })
            ->filter();

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
        self::$loadedModules->each(function (Module $module){
            try {
                $module->stop();
            } catch (\Exception $e) {
                Log::errorWithCause("Failed to stop module", $e);
            }
        });
    }
}

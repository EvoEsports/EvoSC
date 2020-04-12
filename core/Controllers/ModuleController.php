<?php

namespace esc\Controllers;

use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\Module;
use esc\Interfaces\ControllerInterface;
use Illuminate\Support\Collection;

/**
 * Class ModuleController
 *
 * @package esc\Controllers
 */
class ModuleController implements ControllerInterface
{
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

    public static function startModules(string $mode)
    {
        //Boot modules
        Log::info('Starting modules...');

        $coreModules = File::getFilesRecursively(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Modules', '/^[A-Z].+\.php$/');
        $allModules = $coreModules->merge(File::getFilesRecursively(modulesDir(), '/^[A-Z].+\.php$/'));
        $totalStarted = 0;

        $moduleClasses = $allModules
            ->reject(function ($file) {
                return preg_match('/\/[Mm]odules\/[a-z\-]+\/.+\/[A-Z].+/', $file);
            })
            ->mapWithKeys(function ($file) {
                $file = realpath($file);
                return ["esc\\Modules\\" . substr(basename($file), 0, -4) => dirname($file)];
            })
            ->unique()
            ->map(function ($moduleDir, $moduleClass) use ($mode, &$totalStarted) {
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
                    Log::error('Module: ' . $moduleClass . ' [ERROR] ' . $e->getMessage() . ' (not started).', true);
                    return null;
                }

                if (!($instance instanceof Module)) {
                    Log::error("$moduleClass is not a module, but should be (not started).", true);
                    return null;
                }

                /** @var $moduleClass Module */
                $instance::start($mode);
                $instance->setConfigId($configId);
                $instance->setDirectory($moduleDir);
                $instance->setNamespace($moduleClass);
                $instance->setName(preg_replace('#^.+[\\\]#', '', $moduleClass));
                Log::info("Module: $moduleClass [Started]", isVerbose());

                $totalStarted++;

                return $instance;
            })
            ->filter();

        //Boot modules
        Log::cyan("Starting modules finished. $totalStarted modules started.");

        self::$loadedModules = $moduleClasses;
    }
}
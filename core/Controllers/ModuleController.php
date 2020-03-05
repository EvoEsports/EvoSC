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
    private static $loadedModules;

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

        $moduleClasses = $allModules
            ->reject(function ($file) {
                return preg_match('/\/[Mm]odules\/[a-z\-]+\/.+\/[A-Z].+/', $file);
            })
            ->mapWithKeys(function ($file) {
                $file = realpath($file);
                return ["esc\\Modules\\" . substr(basename($file), 0, -4) => dirname($file)];
            })
            ->unique()
            ->map(function ($moduleDir, $moduleClass) use ($mode) {
                $files = scandir($moduleDir);
                $configId = null;
                $config = null;

                foreach ($files as $file) {
                    if (preg_match('/^(.+)\.config\.json$/', $file, $matches)) {
                        $configId = $matches[1];
                    }
                }

                if ($configId == null) {
                    Log::error('Missing config for module: ' . $moduleClass, true);
                    return null;
                } else {
                    $config = ConfigController::getConfig($configId, true);
                    $enabled = isset($config->enabled) ? $config->enabled : true;
                    if (!is_null($enabled) && $enabled == false) {
                        return null;
                    }
                }

                Log::info("Starting $moduleClass.", isVeryVerbose());

                $instance = new $moduleClass();

                if (!($instance instanceof Module)) {
                    Log::error("$moduleClass is not a module, but should be.", true);
                    return null;
                }

                /** @var $moduleClass Module */
                $instance::start($mode);
                $instance->setConfig($config);
                $instance->setDirectory($moduleDir);
                $instance->setNamespace($moduleClass);
                Log::info("Module $moduleClass started.", isVeryVerbose());

                return $instance;
            })
            ->filter();

        //Boot modules
        Log::write('Finished starting modules.');

        self::$loadedModules = $moduleClasses;
    }
}
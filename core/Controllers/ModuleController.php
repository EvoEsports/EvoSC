<?php

namespace esc\Controllers;

use esc\Classes\Log;
use esc\Interfaces\ControllerInterface;
use esc\Models\Player;
use Illuminate\Support\Collection;
use ReflectionMethod;

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
     * Initialize ModuleController.
     */
    public static function init()
    {
        self::$loadedModules = new Collection();
    }

    /**
     * [Bugging] reload a module
     *
     * @param Player $callee
     * @param string $moduleName
     */
    public static function reloadModule(Player $callee, string $moduleName)
    {
        $module = self::getModules()->where('name', $moduleName)->first();

        if ($module) {
            $module->load($callee);
            infoMessage($callee, ' reloads module ', $module)->sendAll();
        }
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

    /**
     * Print module information to the cli (used on boot).
     *
     * @param $module
     */
    public static function outputModuleInformation($module)
    {
        $name    = str_pad($module->name ?? 'n/a', 30, ' ', STR_PAD_RIGHT);
        $author  = str_pad($module->author ?? 'n/a', 30, ' ', STR_PAD_RIGHT);
        // $version = str_pad(sprintf('%.1f', floatval($module->version)), 12, ' ', STR_PAD_RIGHT);
        $version = str_pad(getEscVersion(), 12, ' ', STR_PAD_RIGHT);

        Log::getOutput()->writeln('<fg=green>' . "$name$version$author" . '</>');
    }

    //Load the module information.
    private static function loadModulesInformation(Collection $moduleDirectories)
    {
        $moduleDirectories->each(function ($moduleDirectory) {
            $moduleJson = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, '/../Modules/' . $moduleDirectory . '/module.json');
            if (file_exists($moduleJson)) {
                $json              = file_get_contents($moduleJson);
                $moduleInformation = json_decode($json);
                self::$loadedModules->push($moduleInformation);
            }
        });
    }

    /**
     * Load all modules.
     */
    public static function bootModules()
    {
        $classes = classes();

        //Get modules from classes
        $moduleClasses = $classes->filter(function ($class) {
            if (preg_match('/^esc.Modules./', $class->namespace)) {
                return true;
            }

            return false;
        });

        //Get module directories
        $modules = $moduleClasses->pluck(['dir'])->unique();

        //Load module information
        Log::write('Loading module information');
        self::loadModulesInformation($modules);

        //Output loaded modules
        Log::getOutput()->writeln("");
        Log::getOutput()->writeln('<fg=green>Name                          Version     Author</>');
        Log::getOutput()->writeln('<fg=green>------------------------------------------------------------------------</>');
        self::$loadedModules->each([ModuleController::class, 'outputModuleInformation']);
        Log::getOutput()->writeln("");

        //Boot modules
        Log::write('Booting modules...');

        $moduleClasses->each(function ($module) {
            $files    = scandir(coreDir('Modules' . DIRECTORY_SEPARATOR . $module->dir));
            $configId = null;
            foreach ($files as $file) {
                if (preg_match('/^(.+)\.config\.json$/', $file, $matches)) {
                    $configId = $matches[1];
                }
            }

            if ($configId == null) {
                Log::warning('Missing config: ' . $module->class, isDebug());
            } else {
                $enabled = ConfigController::getConfig($configId . '.enabled');
                if (!is_null($enabled) && $enabled == false) {
                    return;
                }
            }

            if (method_exists($module->namespace, '__construct')) {
                $reflectionMethod = new ReflectionMethod($module->namespace, '__construct');

                if ($reflectionMethod->getNumberOfRequiredParameters() == 0) {
                    //Boot the module
                    $class = new $module->namespace;
                }
            }
        });
    }
}
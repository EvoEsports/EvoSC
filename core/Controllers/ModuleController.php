<?php

namespace esc\Controllers;

use esc\Classes\ChatCommand;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;
use esc\Models\Player;
use Illuminate\Support\Collection;
use ReflectionMethod;

class ModuleController implements ControllerInterface
{
    private static $loadedModules;

    public static function init()
    {
        self::$loadedModules = new Collection();

        AccessRight::createIfNonExistent('module_reload', 'Reload a module.');

        ManiaLinkEvent::add('modules.close', [ModuleController::class, 'hideModules']);
        ManiaLinkEvent::add('module.reload', [ModuleController::class, 'reloadModule'], 'module_reload');

        ChatCommand::add('modules', [ModuleController::class, 'showModules'], 'Display all loaded modules', '//', 'module_reload');
    }

    public static function reloadModule(Player $callee, string $moduleName)
    {
        $module = self::getModules()->where('name', $moduleName)->first();

        if ($module) {
            $module->load($callee);
            ChatController::message(onlinePlayers(), '_info', $callee, ' reloads module ', $module);
        }
    }

    public static function getModules(): Collection
    {
        return self::$loadedModules;
    }

    public static function showModules(Player $player)
    {
        if (!$player->isMasteradmin()) {
            ChatController::message($player, warning('Access denied'));

            return;
        }

        $modules = Template::toString('modules', ['modules' => self::getModules()]);

        Template::show($player, 'components.modal', [
            'id'      => 'ModulesReloader',
            'title'   => 'ModulesReloader $f00(by the love of god, do not touch!)',
            'width'   => 180,
            'height'  => 97,
            'content' => $modules,
        ]);
    }

    public static function hideModules(Player $callee)
    {
        Template::hide($callee, 'modules');
    }

    public static function outputModuleInformation($module)
    {
        $name    = str_pad($module->name ?? 'n/a', 30, ' ', STR_PAD_RIGHT);
        $author  = str_pad($module->author ?? 'n/a', 30, ' ', STR_PAD_RIGHT);
        $version = str_pad($module->version ?? 'n/a', 12, ' ', STR_PAD_RIGHT);

        Log::getOutput()->writeln('<fg=green>' . "$name$version$author" . '</>');
    }

    private static function loadModulesInformation(Collection $moduleDirectories)
    {
        $moduleDirectories->each(function ($moduleDirectory) {
            $moduleJson = __DIR__ . '/../Modules/' . $moduleDirectory . '/module.json';
            if (file_exists($moduleJson)) {
                $json              = file_get_contents($moduleJson);
                $moduleInformation = json_decode($json);
                self::$loadedModules->push($moduleInformation);
            }
        });
    }

    /**
     * Start the modules
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
        Log::logAddLine('Modules', 'Loading module information');
        self::loadModulesInformation($modules);

        //Output loaded modules
        Log::getOutput()->writeln("");
        self::outputModuleInformation(json_decode('{"name":"Name","version":"Version","author":"Author"}'));
        self::outputModuleInformation(json_decode('{"name":"------------------------------","version":"------------","author":"------------------------------"}'));
        self::$loadedModules->each([ModuleController::class, 'outputModuleInformation']);
        Log::getOutput()->writeln("");

        //Boot modules
        Log::logAddLine('Modules', 'Booting modules...');

        $moduleClasses->each(function ($module) {
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
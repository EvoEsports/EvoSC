<?php

namespace esc\Controllers;

use esc\Classes\ChatCommand;
use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\Template;
use esc\Models\Group;
use esc\Models\Player;
use Illuminate\Support\Collection;

class ModuleController
{
    private static $loadedModules;

    public static function init()
    {
        self::$loadedModules = new Collection();

        Template::add('modules', File::get('core/Templates/modules.latte.xml'));

        ManiaLinkEvent::add('modules.close', 'esc\Controllers\ModuleController::hideModules');
        ManiaLinkEvent::add('module.reload', 'esc\Controllers\ModuleController::reloadModule');

        ChatCommand::add('modules', 'esc\Controllers\ModuleController::showModules', 'Display all loaded modules', '//', 'module.reload');
    }

    public static function reloadModule(Player $callee, string $moduleName)
    {
        $module = self::getModules()->where('name', $moduleName)->first();

        if ($module) {
            $module->load($callee);

            foreach (onlinePlayers() as $player) {
                ChatController::message($player, $callee, ' reloads module ', $module);
            }
        }
    }

    public static function getModules(): Collection
    {
        return self::$loadedModules;
    }

    public static function showModules(Player $callee)
    {
        $modules = Template::toString('modules', ['modules' => self::getModules()]);

        Template::show($callee, 'esc.modal', [
            'id' => 'ModulesReloader',
            'width' => 180,
            'height' => 97,
            'content' => $modules
        ]);
    }

    public static function hideModules(Player $callee)
    {
        Template::hide($callee, 'modules');
    }

    public static function loadModules($loadFrom = __DIR__.'/../Modules')
    {
        foreach (array_diff(scandir($loadFrom), array('..', '.', '.gitignore')) as $item) {
            $dir = $loadFrom . '/' . $item;

            if (!file_exists($dir . '/module.json')) {
                Log::error("Missing module.json for [$item]");
                return;
            }

            $moduleData = json_decode(file_get_contents($dir . '/module.json'));

            try {
                require_once "$loadFrom/$item/$moduleData->main.php";
                $module = new Module($moduleData->name ?? $moduleData->main, $moduleData->main);
                $module->load();

                self::$loadedModules->push($module);

                Log::logAddLine('Module', "$item loaded");
            } catch (\Exception $e) {
                Log::error("Could not load module $item: $e");
            }
        }
    }
}
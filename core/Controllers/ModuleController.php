<?php

namespace esc\controllers;

use esc\classes\ChatCommand;
use esc\classes\File;
use esc\classes\Log;
use esc\classes\ManiaLinkEvent;
use esc\classes\Module;
use esc\classes\Template;
use esc\models\Group;
use esc\models\Player;
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

        ChatCommand::add('modules', 'esc\Controllers\ModuleController::showModules', 'Display all loaded modules', '//', [Group::SUPER]);
    }

    public static function reloadModule(Player $callee, string $moduleName)
    {
        $module = self::getModules()->where('name', $moduleName)->first();

        if ($module) {
            $module->load($callee);

            $players = PlayerController::getPlayers()->where('Group', Group::SUPER);

            foreach ($players as $player) {
                ChatController::message($player, $callee, ' reloads module ', $module);
            }
        }
    }

    public
    static function getModules(): Collection
    {
        return self::$loadedModules;
    }

    public
    static function showModules(Player $callee)
    {
        Template::show($callee, 'modules', ['modules' => self::getModules()]);
    }

    public
    static function hideModules(Player $callee)
    {
        Template::hide($callee, 'modules');
    }

    public
    static function loadModules($loadFrom = 'modules')
    {
        foreach (array_diff(scandir($loadFrom), array('..', '.', '.gitignore')) as $item) {
            $dir = $loadFrom . '\\' . $item;

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

                Log::info("## Loaded module $item");
            } catch (\Exception $e) {
                Log::error("Could not load module $item: $e");
            }
        }
    }
}
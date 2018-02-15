<?php

namespace esc\controllers;

use esc\classes\Log;

class ModuleController
{
    public static function loadModules($loadFrom = 'modules')
    {
        foreach (array_diff(scandir($loadFrom), array('..', '.', '.gitignore')) as $item) {
            $dir = $loadFrom . '/' . $item;

            if (!file_exists($dir . '/module.json')) {
                Log::error("Missing module.json for [$item]");
                return;
            }

            $module = json_decode(file_get_contents($dir . '/module.json'));

            try {
                require_once "$loadFrom/$item/$module->main.php";
                $className = "\\$loadFrom\\$item\\$module->main()";
                $test = new $module->main();

                Log::info("Loaded module $item");
            } catch (\Exception $e) {
                Log::error("Could not load module $item: $e");
            }
        }
    }
}
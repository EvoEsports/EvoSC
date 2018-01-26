<?php

namespace esc\classes;

class ModuleHandler
{
    public static function loadModules($loadFrom = 'modules')
    {
        foreach (array_diff(scandir('modules'), array('..', '.')) as $item) {
            $dir = $loadFrom . '/' . $item;

            if (!file_exists($dir . '/module.json')) {
                Log::error("Missing module.json for [$item]");
            }

            $module = json_decode(file_get_contents($dir . '/module.json'));

            try {
                require_once "modules/$item/$module->main.php";
                $className = "\\modules\\$item\\$module->main()";
                $test = new $module->main();
            } catch (\Exception $e) {
                Log::error("Could not load ($item)");
            }
        }
    }
}
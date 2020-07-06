<?php


namespace EvoSC\Controllers;

use EvoSC\Classes\File;
use EvoSC\Interfaces\ControllerInterface;

class ControllerController
{
    /**
     * @param string $mode
     * @param bool $isBoot
     */
    public static function loadControllers(string $mode, bool $isBoot = false)
    {
        HookController::init();
        ModeController::setMode($mode);

        $controllers = File::getFiles(coreDir('Controllers'))->map(function ($file) {
            $class = preg_replace('#^.+[' . DIRECTORY_SEPARATOR . ']Controllers[' . DIRECTORY_SEPARATOR . ']#', '', $file);
            $class = substr($class, 0, -4);
            $class = "EvoSC\\Controllers\\$class";

            return $class;
        });

        foreach ($controllers as $controllerClass) {
            if (new $controllerClass instanceof ControllerInterface) {
                $controllerClass::start($mode, $isBoot);
            }
        }
    }
}
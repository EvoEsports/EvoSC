<?php


namespace EvoSC\Controllers;

use EvoSC\Classes\Controller;
use EvoSC\Classes\File;
use EvoSC\Interfaces\ControllerInterface;
use Illuminate\Support\Collection;

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

        foreach (self::getControllers() as $controllerClass) {
            if (new $controllerClass instanceof ControllerInterface) {
                $controllerClass::start($mode, $isBoot);
            }
        }
    }

    /**
     *
     */
    public static function stopControllers()
    {
        foreach (self::getControllers() as $controllerClass) {
            if (is_subclass_of(new $controllerClass, Controller::class)) {
                $controllerClass::stop();
            }
        }
    }

    /**
     * @return Collection
     */
    private static function getControllers(): Collection
    {
        return File::getFiles(coreDir('Controllers'))->map(function ($file) {
            if (isWindows()) {
                $class = preg_replace('#^.+[' . DIRECTORY_SEPARATOR . '\]Controllers[' . DIRECTORY_SEPARATOR . '\]#', '', $file); // Throws an error of missing ']' otherwise
            } else {
                $class = preg_replace('#^.+[' . DIRECTORY_SEPARATOR . ']Controllers[' . DIRECTORY_SEPARATOR . ']#', '', $file);
            }
            $class = substr($class, 0, -4);
            $class = "EvoSC\\Controllers\\$class";

            return $class;
        });
    }
}
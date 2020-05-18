<?php


namespace EvoSC\Controllers;

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
        $classes = get_declared_classes();

        foreach ($classes as $class) {
            if (preg_match('/^EvoSC.Controllers./', $class)) {
                if (new $class instanceof ControllerInterface) {
                    $class::start($mode, $isBoot);
                }
            }
        }
    }
}
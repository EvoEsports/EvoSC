<?php


namespace esc\Controllers;

use esc\Interfaces\ControllerInterface;

class ControllerController
{
    /**
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function loadControllers(string $mode, bool $isBoot = false)
    {
        foreach (classes() as $class) {
            if (!preg_match('/^esc.Controllers./', $class->namespace)) {
                continue;
            }

            if (new $class->namespace instanceof ControllerInterface) {
                $class->namespace::start($mode, $isBoot);
            }
        }
    }
}
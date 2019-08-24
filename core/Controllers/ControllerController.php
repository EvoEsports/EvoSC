<?php


namespace esc\Controllers;

use esc\Interfaces\ControllerInterface;

class ControllerController
{
    public static function loadControllers(string $mode)
    {
        foreach (classes() as $class) {
            if (!preg_match('/^esc.Controllers./', $class->namespace)) {
                continue;
            }

            if (new $class->namespace instanceof ControllerInterface) {
                $class->namespace::start($mode);
            }
        }
    }
}
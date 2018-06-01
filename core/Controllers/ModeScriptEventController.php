<?php

namespace esc\Controllers;


use esc\Classes\Hook;

class ModeScriptEventController
{
    public static function handleModeScriptCallbacks($modeScriptCallback)
    {
        $name      = $modeScriptCallback[0];
        $arguments = $modeScriptCallback[1];

        switch ($name) {
            default:
                echo "Calling unhandled MSC: $name \n";
                break;
        }

        Hook::fire($name, $arguments);
    }
}
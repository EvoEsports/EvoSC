<?php

namespace esc\Controllers;


use esc\Classes\Hook;

class ModeScriptEventController
{
    public static function handleModeScriptCallbacks($modeScriptCallback)
    {
        if (!$modeScriptCallback) return;

        $modeScriptCallbackName  = $modeScriptCallback[0];
        $modeScriptCallbackArray = $modeScriptCallback[1];

        foreach ($modeScriptCallbackArray as $callback) {
            $name      = $callback[0];
            $arguments = $callback[1];

            switch ($name) {
                default:
                    echo "Calling unhandled MSC: $name \n";
                    break;
            }

            Hook::fire($name, $arguments);
        }
    }
}
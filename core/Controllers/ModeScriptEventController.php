<?php

namespace esc\Controllers;


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
    }
}
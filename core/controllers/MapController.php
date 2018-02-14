<?php

namespace esc\controllers;


use esc\classes\Timer;

class MapController
{
    public static function initialize()
    {
        ChatController::addCommand('add', '\esc\controllers\MapController::addMap', '@');
    }

    public static function addMap(string ...$arguments)
    {
        $mxId = intval($arguments[1]);

        echo "TimeConversionText: " . $arguments[1] . " -> " . Timer::textTimeToMinutes($arguments[1]) . "\n";

        if($mxId == 0){
            //Handle invalid map
        }
    }
}
<?php

namespace esc\controllers;


use esc\classes\Timer;

class CountdownController
{
    public static function init()
    {
        Timer::create('asdf', 'esc\controllers\CountdownController::timerTest', '2s');
    }

    public static function timerTest()
    {
        echo "howdy\n";
    }
}
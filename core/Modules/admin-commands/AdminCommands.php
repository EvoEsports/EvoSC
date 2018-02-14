<?php

use esc\controllers\ChatController;
use esc\controllers\MapController;

class AdminCommands
{
    public function __construct()
    {
        ChatController::addCommand('next', 'AdminCommands::next', 'Skip map instantly', '@');
        ChatController::addCommand('random', 'AdminCommands::random', 'Juke random map', '@');
    }

    public static function next()
    {
        ChatController::messageAll("Going to next map.");
        MapController::next();
    }

    public static function random()
    {
        MapController::setRandomNext();
    }
}
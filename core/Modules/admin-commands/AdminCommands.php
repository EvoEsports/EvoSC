<?php

use esc\controllers\ChatController;
use esc\controllers\MapController;

class AdminCommands
{
    public function __construct()
    {
        ChatController::addCommand('next', 'AdminCommands::next', 'Skip map instantly', '//', ['Admin', 'SuperAdmin']);
    }

    public static function next()
    {
        ChatController::messageAll("Going to next map.");
        MapController::next();
    }
}
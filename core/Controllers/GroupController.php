<?php

namespace esc\Controllers;


use esc\Interfaces\ControllerInterface;
use esc\Models\AccessRight;

class GroupController implements ControllerInterface
{
    public static function init()
    {
        AccessRight::createIfNonExistent('group_edit', 'Add/delete/update groups.');
        AccessRight::createIfNonExistent('group_change', 'Change player group.');
    }
}
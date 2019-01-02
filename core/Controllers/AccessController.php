<?php

namespace esc\Controllers;


use esc\Classes\Database;
use esc\Interfaces\ControllerInterface;
use Illuminate\Database\Schema\Blueprint;

class AccessController implements ControllerInterface
{
    public static function init()
    {
    }

    public static function createTables()
    {
        $seed = [
            ['name' => 'map.skip', 'description' => 'Skip the map instantly'],
            ['name' => 'map.replay', 'description' => 'Queue map for replay'],
            ['name' => 'map.add', 'description' => 'Permanently add map from MX'],
            ['name' => 'map.delete', 'description' => 'Delete map from server'],
            ['name' => 'queue.recent', 'description' => 'Can queue recently played maps'],
            ['name' => 'queue.drop', 'description' => 'Drop maps from queue'],
            ['name' => 'vote.decide', 'description' => 'You can approve/decline votes'],
            ['name' => 'vote.cast', 'description' => 'Create a custom vote'],
            ['name' => 'player.kick', 'description' => 'Kick a player'],
            ['name' => 'player.ban', 'description' => 'Ban a player'],
            ['name' => 'player.mute', 'description' => 'Mute a player'],
            ['name' => 'time', 'description' => 'Can change the countdown time'],
            ['name' => 'group', 'description' => 'Add/delete/update groups'],
            ['name' => 'modules', 'description' => 'Reload modules/templates'],
            ['name' => 'config', 'description' => 'View/edit config'],
        ];
    }
}
<?php

namespace esc\Controllers;


use esc\Classes\Database;
use esc\Models\AccessRight;
use Illuminate\Database\Schema\Blueprint;

class AccessController
{
    public static function init()
    {
        self::createTables();
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
        ];

        Database::create('access-rights', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('description')->nullable();
        }, $seed);

        $groupAccessSeed = [];
        foreach ($seed as $key => $item) {
            array_push($groupAccessSeed, ['group_id' => 1, 'access_right_id' => $key + 1]);
            array_push($groupAccessSeed, ['group_id' => 2, 'access_right_id' => $key + 1]);
        }

        Database::create('access_right_group', function (Blueprint $table) {
            $table->integer('group_id');
            $table->integer('access_right_id');
            $table->unique(['group_id', 'access_right_id']);
        }, $groupAccessSeed);
    }
}
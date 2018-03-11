<?php

namespace esc\controllers;


use esc\Classes\Database;
use esc\Models\AccessRight;
use Illuminate\Database\Schema\Blueprint;

class AccessController
{
    public static function init()
    {
        self::createTables();
    }

    private static function createTables()
    {
        $seed = [
            ['name' => 'skip', 'description' => 'Skip the map'],
            ['name' => 'replay', 'description' => 'Queue map for replay'],
            ['name' => 'vote', 'description' => 'You can approve/decline votes'],
            ['name' => 'kick', 'description' => 'Kick a player'],
            ['name' => 'ban', 'description' => 'Ban a player'],
            ['name' => 'mute', 'description' => 'Mute a player'],
            ['name' => 'time', 'description' => 'Can change the countdown time'],
            ['name' => 'recent', 'description' => 'Can queue recently played maps']
        ];

        Database::create('access-rights', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('description')->nullable();
        }, $seed);

        $groupAccessSeed = [];
        foreach ($seed as $key => $item) {
            array_push($groupAccessSeed, ['group_id' => 1, 'access_id' => $key + 1]);
        }

        Database::create('group_access', function (Blueprint $table) {
            $table->integer('group_id');
            $table->integer('access_id');
            $table->unique(['group_id', 'access_id']);
        }, $groupAccessSeed);
    }
}
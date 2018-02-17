<?php

namespace esc\controllers;


use esc\classes\Database;
use Illuminate\Database\Schema\Blueprint;

class GroupController
{
    public static function init()
    {
        self::createTables();
    }

    private static function createTables()
    {
        $seed = [
            ['id' => 1, 'Name' => 'SuperAdmin', 'Color' => '3f3', 'Protected' => true],
            ['id' => 2, 'Name' => 'Admin', 'Color' => 'f33', 'Protected' => true],
            ['id' => 3, 'Name' => 'Moderator', 'Color' => 'f93', 'Protected' => true],
            ['id' => 4, 'Name' => 'Player', 'Color' => config('color.primary'), 'Protected' => true],
        ];

        Database::create('groups', function(Blueprint $table){
            $table->increments('id');
            $table->string('Name')->unique();
            $table->string('Color')->default(config('color.primary'));
            $table->boolean('Protected')->default(false);
        }, $seed);
    }
}
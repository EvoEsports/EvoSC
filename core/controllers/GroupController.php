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
            ['Name' => 'SuperAdmin'],
            ['Name' => 'Admin'],
            ['Name' => 'Moderator'],
            ['Name' => 'Player'],
        ];

        Database::create('groups', function(Blueprint $table){
            $table->increments('id');
            $table->string('Name')->unique();
        }, $seed);
    }
}
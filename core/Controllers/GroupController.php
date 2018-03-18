<?php

namespace esc\Controllers;


use esc\classes\Database;
use Illuminate\Database\Schema\Blueprint;

class GroupController
{
    public static function init()
    {
        self::createTables();
    }

    public static function createTables()
    {
        $seed = [
            ['id' => 1, 'Name' => 'Masteradmin', 'Protected' => true],
        ];

        Database::create('groups', function(Blueprint $table){
            $table->increments('id');
            $table->string('Name')->unique();
            $table->boolean('Protected')->default(false);
        }, $seed);
    }
}
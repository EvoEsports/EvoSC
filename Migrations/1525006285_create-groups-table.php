<?php

namespace esc\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Name')->unique();
            $table->string('chat_prefix')->nullable();
            $table->string('color')->nullable();
            $table->boolean('Protected')->default(false);
        });

        $seed = [
            ['id' => 1, 'Name' => 'Masteradmin', 'Protected' => true],
            ['id' => 2, 'Name' => 'Admin', 'Protected' => true],
            ['id' => 3, 'Name' => 'Moderator', 'Protected' => false],
            ['id' => 4, 'Name' => 'Player', 'Protected' => true],
        ];

        $schemaBuilder->getConnection()->table('groups')->insert($seed);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->drop('groups');
    }
}
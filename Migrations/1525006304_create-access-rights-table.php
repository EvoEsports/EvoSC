<?php

namespace esc\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateAccessRightsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('access-rights', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('description')->nullable();
        });

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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->drop('access-rights');
    }
}
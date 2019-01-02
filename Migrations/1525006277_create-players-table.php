<?php

namespace esc\Migrations;

use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePlayersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Login')->unique();
            $table->string('NickName')->default("unset");
            $table->integer('Group')->default(3);
            $table->string('path')->nullable();
            $table->integer('Score')->default(0);
            $table->integer('player_id')->default(0);
            $table->integer('spectator_status')->default(0);
            $table->integer('MaxRank')->default(15);
            $table->boolean('banned')->default(false);
            $table->text('user_settings')->nullable();
            $table->dateTime('last_visit')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->drop('players');
    }
}
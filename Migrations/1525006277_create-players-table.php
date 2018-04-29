<?php

namespace esc\Migrations;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlayersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Login')->unique();
            $table->string('NickName')->default("unset");
            $table->integer('Group')->default(3);
            $table->integer('Score')->default(0);
            $table->boolean('Online')->default(false);
            $table->integer('Afk')->default(0);
            $table->integer('spectator_status')->default(0);
            $table->integer('MaxRank')->default(15);
            $table->boolean('Banned')->default(false);
            $table->text('user_settings')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('players');
    }
}
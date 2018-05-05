<?php

namespace esc\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateUserSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->table('players', function (Blueprint $table) {
            $table->dropColumn(['user_settings']);
        });

        $schemaBuilder->create('user-settings', function (Blueprint $table) {
            $table->integer('player_id');
            $table->string('name');
            $table->string('value');
            $table->unique(['player_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->table('players', function (Blueprint $table) {
            $table->text('user_settings')->nullable();
        });

        $schemaBuilder->drop('user-settings');
    }
}
<?php

namespace EvoSC\Migrations;

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
            $table->string('player_Login', 64);
            $table->string('name', 50);
            $table->string('value');
            $table->unique(['player_Login', 'name'], 'login_setting_unique');
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

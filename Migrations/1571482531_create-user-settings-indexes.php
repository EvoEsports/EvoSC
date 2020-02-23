<?php

namespace esc\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateUserSettingsIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->table('user-settings', function (Blueprint $table) {
            $table->index(['player_Login', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->table('user-settings', function (Blueprint $table) {
            $table->dropIndex(['player_Login', 'name']);
        });
    }
}
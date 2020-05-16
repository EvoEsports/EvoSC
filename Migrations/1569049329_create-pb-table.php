<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreatePbTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('pbs', function (Blueprint $table) {
            $table->integer('map_id');
            $table->integer('player_id');
            $table->integer('score');
            $table->text('checkpoints');
            $table->timestamps();
            $table->unique(['map_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->dropIfExists('pbs');
    }
}
<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateMatchStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('match_stats', function (Blueprint $table) {
            $table->string('map_uid');
            $table->string('login');
            $table->string('ubiname');
            $table->string('team')->nullable();
            $table->integer('position');
            $table->integer('round');
            $table->integer('total_points')->nullable();
            $table->integer('score')->nullable();
            $table->text('checkpoints')->nullable();
            $table->tinyInteger('end_match')->default(0);
            $table->dateTime('time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->dropIfExists('match_stats');
    }
}
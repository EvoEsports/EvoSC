<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateLocalRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @param Builder $schemaBuilder
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('local-records', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('Player');
            $table->integer('Map');
            $table->integer('Score');
            $table->integer('Rank');
            $table->text('Checkpoints')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @param Builder $schemaBuilder
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->drop('local-records');
    }
}
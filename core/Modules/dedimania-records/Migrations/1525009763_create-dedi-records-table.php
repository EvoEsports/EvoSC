<?php

namespace esc\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateDediRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('dedi-records', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('Map');
            $table->integer('Player');
            $table->integer('Score');
            $table->integer('Rank');
            $table->text('Checkpoints')->nullable();
            $table->boolean('New')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->drop('dedi-records');
    }
}
<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateInfoMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @param Builder $schemaBuilder
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('info-messages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('text');
            $table->integer('delay');
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
        $schemaBuilder->drop('info-messages');
    }
}
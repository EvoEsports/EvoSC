<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class AddScheduleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('schedule', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->string('event');
            $table->text('arguments')->nullable();
            $table->dateTime('execute_at');
            $table->boolean('failed')->nullable();
            $table->text('stack_trace')->nullable();
            $table->unsignedBigInteger('scheduled_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->dropIfExists('schedule');
    }
}
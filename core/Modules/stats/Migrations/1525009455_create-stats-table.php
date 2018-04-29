<?php

namespace esc\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('stats', function (Blueprint $table) {
            $table->integer('Player')->primary();
            $table->integer('Visits')->default(0);
            $table->integer('Playtime')->default(0);
            $table->integer('Finishes')->default(0);
            $table->integer('Locals')->default(0);
            $table->integer('Ratings')->default(0);
            $table->integer('Wins')->default(0);
            $table->integer('Donations')->default(0);
            $table->integer('Score')->default(0);
            $table->integer('Rank')->default(0);
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
        Schema::drop('stats');
    }
}
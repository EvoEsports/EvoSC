<?php

namespace esc\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateMapsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('maps', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uid')->unique();
            $table->integer('author');
            $table->text('gbx')->nullable();
            $table->text('mx_details')->nullable();
            $table->text('mx_world_record')->nullable();
            $table->string('filename')->unique();
            $table->integer('plays')->default(0);
            $table->integer('cooldown')->default(0);
            $table->dateTime('last_played')->nullable();
            $table->boolean('enabled')->default(0);
            $table->string('checksum')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->drop('maps');
    }
}
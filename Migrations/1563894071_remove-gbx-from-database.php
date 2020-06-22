<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class UpdateMapsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->table('maps', function (Blueprint $table) {
            $table->dropColumn('gbx');

            $table->string('name')->nullable();
            $table->string('environment')->nullable();
            $table->string('title_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->table('maps', function (Blueprint $table) {
            $table->text('gbx')->nullable();

            $table->dropColumn('name');
            $table->dropColumn('environment');
            $table->dropColumn('title_id');
        });
    }
}
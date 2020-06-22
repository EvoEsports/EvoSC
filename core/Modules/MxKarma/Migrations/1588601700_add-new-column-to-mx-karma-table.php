<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class AddNewColumnToMxKarmaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @param Builder $schemaBuilder
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->table('mx-karma', function (Blueprint $table) {
            $table->boolean('new')->default(0);
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
        $schemaBuilder->table('mx-karma', function (Blueprint $table) {
            $table->dropColumn('new');
        });
    }
}
<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class UpdateAcessRightsGroupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->table('access_right_group', function (Blueprint $table) {
            $table->string('access_right_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->table('access_right_group', function (Blueprint $table) {
            $table->dropColumn('access_right_name');
        });
    }
}
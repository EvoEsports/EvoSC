<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class AddIndexesToAccessRightsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->table('access-rights', function (Blueprint $table) {
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->table('access-rights', function (Blueprint $table) {
            $table->dropIndex('name');
        });
    }
}
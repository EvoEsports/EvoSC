<?php

namespace EvoSC\Migrations;

use EvoSC\Classes\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class UpdateGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->table('groups', function (Blueprint $table) {
            $table->unsignedTinyInteger('unrestricted')->default(0);
            $table->integer('security_level')->default(0);
        });

        $schemaBuilder->getConnection()
            ->table('groups')
            ->where('id', '=', 1)
            ->update([
                'unrestricted'   => 1,
                'security_level' => 10
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->table('groups', function (Blueprint $table) {
            $table->dropColumn('unrestricted');
            $table->dropColumn('security_level');
        });
    }
}
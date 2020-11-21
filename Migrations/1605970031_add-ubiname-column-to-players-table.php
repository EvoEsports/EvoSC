<?php

namespace EvoSC\Migrations;

use EvoSC\Classes\Database;
use EvoSC\Classes\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class AddUbinameColumnToPlayersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->table('players', function (Blueprint $table) {
            $table->string('ubisoft_name')->nullable()->after('NickName');
        });

        Database::init();
        DB::raw('UPDATE players SET ubisoft_name = NickName;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->table('players', function (Blueprint $table) {
            $table->dropColumn('ubisoft_name');
        });
    }
}
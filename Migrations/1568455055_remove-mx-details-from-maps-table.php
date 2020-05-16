<?php

namespace EvoSC\Migrations;

use EvoSC\Classes\Cache;
use EvoSC\Classes\File;
use EvoSC\Models\Map;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class RemoveMxDetailsFromMapsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        if (!File::dirExists(cacheDir('mx-details'))) {
            File::makeDir(cacheDir('mx-details'));
        }
        if (!File::dirExists(cacheDir('mx-wr'))) {
            File::makeDir(cacheDir('mx-wr'));
        }

        $schemaBuilder->table('maps', function (Blueprint $schema) {
            $schema->dropColumn('mx_details');
            $schema->dropColumn('mx_world_record');
            $schema->integer('mx_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->table('maps', function (Blueprint $schema) {
            $schema->text('mx_details')->nullable();
            $schema->text('mx_world_record')->nullable();
        });
    }
}
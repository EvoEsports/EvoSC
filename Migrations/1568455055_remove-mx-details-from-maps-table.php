<?php

namespace esc\Migrations;

use esc\Classes\Cache;
use esc\Classes\File;
use esc\Models\Map;
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
            $schema->integer('mx_id')->nullable();
        });

        Map::all()->each(function (Map $map) {
            if (isset($map->mx_details->TrackID)) {
                $mxId = $map->mx_details->TrackID;
                $map->update(['mx_id' => $mxId]);

                Cache::put("mx-details/$mxId", $map->mx_details[0], now()->addMinutes(30));
            }

            if (isset($map->mx_world_record)) {
                Cache::put("mx-wr/$mxId", $map->mx_world_record, now()->addMinutes(30));
            }
        });

        $schemaBuilder->table('maps', function (Blueprint $schema) {
            $schema->dropIfExists('mx_details');
            $schema->dropIfExists('mx_world_record');
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

        Map::all()->each(function (Map $map) {
            if (!$map->mx_id) {
                return;
            }

            if (Cache::has('mx-details/'.$map->mx_id)) {
                $map->update([
                    'mx_details' => Cache::get('mx-details/'.$map->mx_id)
                ]);
            }

            if (Cache::has('mx-wr/'.$map->mx_id)) {
                $map->update([
                    'mx_world_record' => Cache::get('mx-wr/'.$map->mx_id)
                ]);
            }
        });

        if (File::dirExists(cacheDir('mx-details'))) {
            File::delete(cacheDir('mx-details'));
        }
        if (File::dirExists(cacheDir('mx-wr'))) {
            File::delete(cacheDir('mx-wr'));
        }
    }
}
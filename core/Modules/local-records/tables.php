<?php

if (!$capsule->getConnection()->getSchemaBuilder()->hasTable('players')) {
    \esc\classes\Log::info("Creating table: players");
    $capsule->getConnection()->getSchemaBuilder()->create('players', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->increments('id');
        $table->string('Login')->unique();
        $table->string('NickName')->default("");
        $table->integer('Visits')->default(0);
        $table->float('LadderScore');
    });
}

if (!$capsule->getConnection()->getSchemaBuilder()->hasTable('maps')) {
    \esc\classes\Log::info("Creating table: maps");
    $capsule->getConnection()->getSchemaBuilder()->create('maps', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->increments('id');
        $table->string('UId');
        $table->string('MXid');
        $table->string('name');
    });
}

if (!$capsule->getConnection()->getSchemaBuilder()->hasTable('local-records')) {
    \esc\classes\Log::info("Creating table: local-records");
    $capsule->getConnection()->getSchemaBuilder()->create('local-records', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->increments('id');
        $table->integer('map');
        $table->integer('player');
        $table->integer('score');
    });
}
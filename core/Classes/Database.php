<?php

namespace esc\Classes;


use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

class Database
{
    private static $capsule;

    public static function init()
    {
        self::connect();
    }

    private static function connect()
    {
        Log::info("Connecting to database...");

        $capsule = new Capsule();

        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => config('database.host'),
            'database' => config('database.db'),
            'username' => config('database.user'),
            'password' => config('database.password'),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => config('database.prefix'),
        ]);

        $capsule->setAsGlobal();

        $capsule->bootEloquent();

        if ($capsule->getConnection() == null) {
            Log::error("Database connection failed. Exiting.");
            exit(2);
        }

        self::$capsule = $capsule;

        Log::info("Database connected.");
    }

    public static function getConnection(): Connection
    {
        return self::$capsule->getConnection();
    }

    public static function create(string $table, $callback, array $seed = null)
    {
        if (!self::hasTable($table)) {
            Log::info("Creating table $table.");
            self::getConnection()->getSchemaBuilder()->create($table, $callback);

            if($seed){
                Log::info("Seeding table $table.");
                foreach($seed as $item){
                    self::getConnection()->table($table)->insert($item);
                }
            }
        }
    }

    public static function hasTable(string $table): bool
    {
        return self::getConnection()->getSchemaBuilder()->hasTable($table);
    }
}
<?php

namespace esc\Classes;


use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

/**
 * Class Database
 *
 * Handles all database functionality with relationships and query-builder.
 * {@see https://laravel.com/docs/5.8/queries}
 *
 * @package esc\Classes
 */
class Database
{
    /**
     * @var Capsule
     */
    private static Capsule $capsule;

    /**
     * Start a keep-alive connection to the database and boot Eloquent.
     * {@see https://laravel.com/docs/5.8/eloquent-relationships}
     */
    public static function init()
    {
        Log::info("Connecting to database...");

        $capsule = new Capsule();

        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => config('database.host'),
            'database'  => config('database.db'),
            'username'  => config('database.user'),
            'password'  => config('database.password'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => config('database.prefix'),
        ]);

        $capsule->setAsGlobal();

        $capsule->bootEloquent();

        if ($capsule->getConnection() == null) {
            Log::error("Database connection failed. Exiting.");
            exit(2); //IO-Error
        }

        self::$capsule = $capsule;

        Log::info("Database connected.");
    }

    /**
     * Get the database-connection
     *
     * @return Connection
     */
    public static function getConnection(): Connection
    {
        return self::$capsule->getConnection();
    }

    /**
     * Create a new database-table.
     *
     * @param string     $table
     * @param            $callback
     * @param array|null $seed
     */
    public static function create(string $table, $callback, array $seed = null)
    {
        if (!self::hasTable($table)) {
            Log::info("Creating table $table.");
            self::getConnection()->getSchemaBuilder()->create($table, $callback);

            if ($seed) {
                Log::info("Seeding table $table.");
                foreach ($seed as $item) {
                    self::getConnection()->table($table)->insert($item);
                }
            }
        }
    }

    /**
     * Check if a database-table exists.
     *
     * @param string $table
     *
     * @return bool
     */
    public static function hasTable(string $table): bool
    {
        return self::getConnection()->getSchemaBuilder()->hasTable($table);
    }
}
<?php


namespace EvoSC\Classes;


use Illuminate\Database\Query\Builder;

class DB
{
    /**
     * @param  string  $tableName
     * @return Builder
     */
    public static function table(string $tableName)
    {
        return Database::getConnection()->table($tableName);
    }

    /**
     * @param callable $transaction
     * @return mixed
     * @throws \Throwable
     */
    public static function transaction(callable $transaction)
    {
        return Database::getConnection()->transaction($transaction);
    }

    /**
     * @param  string  $query
     * @return bool
     */
    public static function raw(string $query)
    {
        return Database::getConnection()->statement($query);
    }
}
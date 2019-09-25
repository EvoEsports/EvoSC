<?php


namespace esc\Classes;


class DB
{
    /**
     * @param  string  $tableName
     * @return \Illuminate\Database\Query\Builder
     */
    public static function table(string $tableName)
    {
        return Database::getConnection()->table($tableName);
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
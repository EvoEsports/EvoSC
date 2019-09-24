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
}
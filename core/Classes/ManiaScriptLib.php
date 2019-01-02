<?php

namespace esc\Classes;


class ManiaScriptLib
{
    private static $library;

    public static function add(string $id, string $script)
    {
        if (self::$library == null) {
            self::$library = collect();
        }

        self::$library->put($id, $script);
    }

    public static function get(string $id): string
    {
        if (self::$library != null && self::$library->contains($id)) {
            return self::$library->get($id);
        }

        return '<unknown_lib>';
    }
}
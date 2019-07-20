<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Log;

class AdminServ
{
    public function __construct()
    {
        Hook::add('AdminServ.Map.Added', [self::class, 'mapAdded']);
        Hook::add('AdminServ.Map.Deleted', [self::class, 'mapDeleted']);
    }

    public static function mapAdded(...$arguments)
    {
        Log::write('AdminServ', json_encode($arguments));
    }

    public static function mapDeleted(...$arguments)
    {
        Log::write('AdminServ', json_encode($arguments));
    }
}
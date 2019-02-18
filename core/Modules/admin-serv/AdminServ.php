<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Log;

class AdminServ
{
    public function __construct()
    {
        Hook::add('AdminServ.Map.Added', [self::class, 'mapAdded']);
    }

    public static function mapAdded(...$arguments)
    {
        Log::logAddLine('AdminServ', json_encode($arguments));
    }
}
<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Module;
use esc\Interfaces\ModuleInterface;

class AdminServ extends Module implements ModuleInterface
{
    public function __construct()
    {
        Hook::add('AdminServ.Map.Added', [self::class, 'mapAdded']);
        Hook::add('AdminServ.Map.Deleted', [self::class, 'mapDeleted']);
    }

    public static function mapAdded(...$arguments)
    {
        Log::write(json_encode($arguments));
    }

    public static function mapDeleted(...$arguments)
    {
        Log::write(json_encode($arguments));
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}
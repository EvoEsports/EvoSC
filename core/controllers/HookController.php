<?php

namespace esc\controllers;


use esc\classes\Hook;
use esc\classes\Log;
use Illuminate\Database\Eloquent\Collection;

class HookController
{
    private static $hooks;

    public static function initialize()
    {
        self::$hooks = new Collection();
    }

    private static function getHooks(): ?Collection
    {
        return self::$hooks;
    }

    public static function add(string $event, string $staticFunction)
    {
        $hooks = self::getHooks();
        $hook = new Hook($event, $staticFunction);

        if($hooks){
            self::getHooks()->add($hook);
            Log::hook("Added $event -> $staticFunction");
        }

    }

    public static function call($event, $arguments = null)
    {
        $hooks = self::getHooks()->filter(function (Hook $hook) use ($event) {
            return $hook->getEvent() == $event;
        });

        foreach($hooks as $hook){
            $hook->execute($arguments);
        }
    }

    public static function handleCallbacks($callbacks)
    {
        foreach ($callbacks as $callback) {
            self::call($callback[0], $callback[1]);
        }
    }
}
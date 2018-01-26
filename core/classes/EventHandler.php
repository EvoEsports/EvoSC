<?php

namespace esc\classes;


class EventHandler
{
    private static $events = [];

    public static function registerEvent($event, $function, $name = null)
    {
        if (!array_key_exists($event, self::$events)) {
            self::$events[$event] = [];
        }

        array_push(self::$events[$event], $function);
    }

    public static function callEvent($event)
    {
        if(!array_key_exists($event, self::$events)){
            Log::error("Unregistered event called: $event");
            return;
        }

        foreach(self::$events[$event] as $execute){
            $execute();
        }
    }
}
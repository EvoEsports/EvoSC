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

        Log::info("Registering event: $event -> $function");

        array_push(self::$events[$event], $function);
    }

    public static function callEvent($event, $arguments = null)
    {
        Log::info("Calling event $event");

        if(!array_key_exists($event, self::$events)){
            return;
        }

        foreach(self::$events[$event] as $execute){
            Log::info("Event ($event): $execute");
            call_user_func_array($execute, $arguments);
        }
    }

    public static function handleCallbacks($callbacks){
        foreach($callbacks as $callback){
            self::callEvent($callback[0], $callback[1]);
        }
    }
}
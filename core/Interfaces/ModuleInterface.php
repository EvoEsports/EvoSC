<?php


namespace esc\Interfaces;


interface ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function start(string $mode);
}
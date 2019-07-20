<?php


namespace esc\Interfaces;


interface ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     */
    public static function up(string $mode);

    /**
     * Called when the module is unloaded
     */
    public static function down();
}
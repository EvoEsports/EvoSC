<?php

namespace esc\Interfaces;

interface ModuleInterface
{
    /**
     * Function called on controller start
     *
     * @return mixed
     */
    public static function boot();

    /**
     * Called when the module is reloaded
     *
     * @return mixed
     */
    public static function reload();
}
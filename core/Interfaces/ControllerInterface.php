<?php

namespace esc\Interfaces;


interface ControllerInterface
{
    /**
     * Method called on controller boot.
     */
    public static function init();

    /**
     * Method called on controller start and mode change
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot);
}
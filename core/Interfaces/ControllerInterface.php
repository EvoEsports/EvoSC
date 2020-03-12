<?php

namespace esc\Interfaces;


interface ControllerInterface
{
    /**
     * Method called once on controller boot.
     */
    public static function init();

    /**
     * Method called after controller boot and at mode changes.
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot);
}
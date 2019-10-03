<?php

namespace esc\Interfaces;


interface ControllerInterface
{
    /**
     * Method called on controller boot.
     */
    public static function init();

    /**
     * @param  string  $mode
     * @param  bool  $isBoot
     * @return mixed
     */
    public static function start(string $mode, bool $isBoot);
}
<?php

namespace esc\Interfaces;


interface ControllerInterface
{
    /**
     * Method called on controller boot.
     */
    public static function init();

    /**
     * @param string $mode
     */
    public static function start($mode);
}
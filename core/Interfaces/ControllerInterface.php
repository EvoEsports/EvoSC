<?php

namespace esc\Interfaces;


interface ControllerInterface
{
    /**
     * Method called on controller boot.
     */
    public static function init();
}
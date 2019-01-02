<?php

namespace esc\Interfaces;


interface ControllerInterface
{
    /**
     * Method called on boot.
     *
     * @return mixed
     */
    public static function init();
}
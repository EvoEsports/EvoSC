<?php


namespace EvoSC\Interfaces;


interface ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed
     */
    public static function start(string $mode, bool $isBoot = false);
}
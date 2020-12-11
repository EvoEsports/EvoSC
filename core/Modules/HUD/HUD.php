<?php


namespace EvoSC\Modules\HUD;


use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Controllers\ConfigController;
use EvoSC\Interfaces\ModuleInterface;

class HUD extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::setProperties(config('hud.settings'));
    }

    /**
     * Set UI properties
     *
     * @param $uiProperties
     */
    public static function setProperties($uiProperties)
    {
        Server::triggerModeScriptEvent('Common.UIModules.SetProperties', [json_encode([
            'uimodules' => $uiProperties
        ])]);
    }
}
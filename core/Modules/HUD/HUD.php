<?php


namespace EvoSC\Modules\HUD;


use EvoSC\Classes\AwaitModeScriptResponse;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ModuleInterface;

class HUD extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::setProperties();
    }

    /**
     * Get UI properties
     */
    public static function getProperties()
    {
        $responseId = Server::triggerModeScriptEvent('Common.UIModules.GetProperties');
        AwaitModeScriptResponse::add($responseId, function ($data) {
            dump($data);
        });
    }

    /**
     * Set UI properties
     */
    public static function setProperties()
    {
        Server::triggerModeScriptEvent('Common.UIModules.SetProperties', [
            'uimodules' => [
                'id' => '',
                'position' => '',
                'scale' => '',
            ]
        ]);
    }
}
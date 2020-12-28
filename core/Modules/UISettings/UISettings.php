<?php

namespace EvoSC\Modules\UISettings;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\QuickButtons\QuickButtons;

class UISettings extends Module implements ModuleInterface
{

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'sendUiSettings']);
        Hook::add('Trackmania.Event.StartLine', [self::class, 'rearrangeUi']);

        ManiaLinkEvent::add('ui.settings', [self::class, 'mleShowSettingsWindow']);
        ManiaLinkEvent::add('ui.save', [self::class, 'mleSaveSettings']);

        QuickButtons::addButton('', 'UI Settings', 'ui.settings');
    }

    public static function mleSaveSettings(Player $player, ...$data)
    {
        $player->setSetting('ui', implode(',', $data));
        successMessage('UI settings saved.')->send($player);
        self::sendUiSettings($player);
    }

    public static function mleShowSettingsWindow(Player $player)
    {
        $settings = $player->setting('ui');
        Template::show($player, 'UISettings.manialink', compact('settings'));
    }

    public static function sendUiSettings(Player $player)
    {
        $settings = $player->setting('ui');
        Template::show($player, 'UISettings.update', compact('settings'), false, 20);
    }

    public static function rearrangeUi(Player $player)
    {
        Template::show($player, 'UISettings.rearrange');
    }
}
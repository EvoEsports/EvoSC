<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Models\Player;

class AlterUi
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'connect']);

        self::setUiProperties();
    }

    public static function connect(Player $player)
    {
        self::setUiProperties();
    }

    //https://github.com/maniaplanet/script-xmlrpc/blob/master/XmlRpcListing.md#trackmaniauisetproperties
    public static function setUiProperties()
    {
        $properties = '';

        $properties .= sprintf('<map_info visible="false" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<live_info visible="true" pos="-160.0 -60.0 0.0" />'); //Player made first place/joined server message
        $properties .= sprintf('<opponents_info visible="true" />');
        $properties .= sprintf('<chat visible="true" offset="0.0" linecount="8.0" />');
        $properties .= sprintf('<checkpoint_list visible="false" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<checkpoint_ranking visible="false" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<countdown visible="true" pos="155.0 0.0 0.0" />');
        $properties .= sprintf('<go visible="true" />');
        $properties .= sprintf('<chrono visible="false" pos="146.0 -85.0 100.0" />');
        $properties .= sprintf('<speed_and_distance visible="false" pos="152.0 -75.0 0.0" />');
        $properties .= sprintf('<personal_best_and_rank visible="false" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<position visible="true" pos="140.0 -88.0 0.0" />'); //player position
        $properties .= sprintf('<checkpoint_time visible="true" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<chat_avatar visible="false" />');
        $properties .= sprintf('<warmup visible="false" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<endmap_ladder_recap visible="false" />');
        $properties .= sprintf('<multilap_info visible="false" />');
        $properties .= sprintf('<spectator_info visible="false" pos="0.0 0.0 0.0" />');

        \esc\Classes\Server::triggerModeScriptEvent('Trackmania.UI.SetProperties', "<ui_properties>$properties</ui_properties>");
    }
}
<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Controllers\KeyController;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class AlterUi
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [AlterUi::class, 'show']);
    }

    public static function show(Player $player)
    {
        $properties = '';

        $properties .= sprintf('<map_info visible="0" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<live_info visible="1" pos="-160.0 -60.0 0.0" />'); //Player made first place/joined server message
        $properties .= sprintf('<opponents_info visible="1" />');
        $properties .= sprintf('<chat visible="1" offset="0.0" linecount="8.0" />');
        $properties .= sprintf('<checkpoint_list visible="0" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<checkpoint_ranking visible="0" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<countdown visible="1" pos="155.0 0.0 0.0" />');
        $properties .= sprintf('<go visible="1" />');
        $properties .= sprintf('<chrono visible="0" pos="146.0 -85.0 100.0" />');
        $properties .= sprintf('<speed_and_distance visible="0" pos="152.0 -75.0 0.0" />');
        $properties .= sprintf('<personal_best_and_rank visible="0" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<position visible="1" pos="140.0 -88.0 0.0" />'); //player position
        $properties .= sprintf('<checkpoint_time visible="1" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<chat_avatar visible="0" />');
        $properties .= sprintf('<warmup visible="0" pos="0.0 0.0 0.0" />');
        $properties .= sprintf('<endmap_ladder_recap visible="0" />');
        $properties .= sprintf('<multilap_info visible="0" />');
        $properties .= sprintf('<spectator_info visible="0" pos="0.0 0.0 0.0" />');

        try {
            \esc\Classes\Server::rpc()->triggerModeScriptEvent('Trackmania.UI.SetProperties', ["<ui_properties>$properties</ui_properties>"]);
        } catch (\Maniaplanet\DedicatedServer\InvalidArgumentException $e) {
            echo $e->getMessage();
        }
    }
}
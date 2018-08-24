<?php

namespace esc\Classes;


use esc\Models\Player;

class MatchSettings extends \SimpleXMLElement
{
    public $filename;
    public $id;

    private static $actions = [
        'mss' => [self::class, 'updateModeScriptSettings'],
        'map' => [self::class, 'updateMap'],
    ];

    public function handle(Player $player, string ...$cmd)
    {
        $command = array_shift($cmd);

        array_unshift($cmd, $player);

        if (array_key_exists($command, self::$actions)) {
            call_user_func_array(self::$actions[$command], $cmd);
        }
    }

    public function updateModeScriptSettings(Player $player, string $name, string $type, string $value)
    {
        foreach ($this->mode_script_settings->setting as $element) {
            if ($element['name'] == $name) {
                $element['value'] = $value;
            }
        }

        //Update file
        $file = config('server.base') . '/UserData/Maps/MatchSettings/' . $this->filename;
        $this->saveXML($file);
    }

    public function updateMap(Player $player, string $mapUid, string $enabledString)
    {
        $enabled = $enabledString == '1';
    }
}
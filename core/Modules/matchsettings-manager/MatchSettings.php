<?php

namespace esc\Classes;


use esc\Models\Player;
use esc\Modules\MatchSettingsManager;

class MatchSettings extends \SimpleXMLElement
{
    public $filename;
    public $id;

    private static $actions = [
        'mss' => [self::class, 'updateModeScriptSettings']
    ];

    public function handle(Player $player, string ...$cmd)
    {
        $command = array_shift($cmd);

        array_unshift($cmd, $player);

        if (array_key_exists($command, self::$actions)) {
            call_user_func_array(self::$actions[$command], $cmd);
        }
    }

    public function updateModeScriptSettings(Player $player, $name, $type, $value)
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
}
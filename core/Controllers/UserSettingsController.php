<?php


namespace esc\Controllers;


use esc\Classes\DB;
use esc\Classes\ManiaLinkEvent;
use esc\Interfaces\ControllerInterface;
use esc\Models\Player;

class UserSettingsController implements ControllerInterface
{
    /**
     * Method called on controller boot.
     */
    public static function init()
    {
    }

    /**
     * Method called on controller start and mode change
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot)
    {
        ManiaLinkEvent::add('user_setting_save', [self::class, 'saveUserSetting']);
    }

    public static function saveUserSetting(Player $player, string $settingId, ...$data)
    {
        if (!is_object($data)) {
            if (count($data) > 1) {
                $data = implode($data);
            }else{
                $data = $data[0];
            }
        }

        if (!is_string($data)) {
            $data = json_encode($data);
        }

        DB::table('user-settings')->updateOrInsert([
            'player_Login' => $player->Login,
            'name' => $settingId
        ], [
            'value' => $data
        ]);
    }
}
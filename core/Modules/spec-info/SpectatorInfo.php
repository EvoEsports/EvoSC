<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Models\Player;

class SpectatorInfo
{
    private static $specTargets;

    public function __construct()
    {
        Hook::add('PlayerInfoChanged', [self::class, 'playerInfoChanged']);
    }

    public static function playerInfoChanged(Player $player)
    {
        $targetId = $player->spectator_status->currentTargetId;

        if ($targetId == 0) {
            return;
        }

        try {
            $target = Player::wherePlayerId($targetId)->firstOrFail();
        } catch (\Exception $e) {
            Log::logAddLine('SoectatorInfo', 'Failed to get target: ' . $e->getMessage());
            createCrashReport($e);
        }

        var_dump($target->NickName);
    }
}
<?php

namespace esc\Modules;

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Models\AccessRight;
use esc\Models\Player;
use Illuminate\Support\Collection;

class CPRecords
{
    private static $checkpoints;

    public function __construct()
    {
        self::clearCheckpoints();

        AccessRight::createIfNonExistent('cpr.reset', 'Reset top checkpoints');

        Hook::add('ShowScores', [CPRecords::class, 'clearCheckpoints']);
        Hook::add('PlayerCheckpoint', [CPRecords::class, 'playerCheckpoint']);
        Hook::add('PlayerConnect', [CPRecords::class, 'playerConnect']);

        ManiaLinkEvent::add('cpr.reset', [self::class, 'reset'], 'cpr.reset');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Reset Checkpoints', 'cpr.reset', 'map.reset');
        }
    }

    public static function clearCheckpoints(...$args)
    {
        self::$checkpoints = [];
        self::showCheckpointRecords(true);
    }

    private static function getCheckpoint(int $id): ?Checkpoint
    {
        if (array_key_exists($id, self::$checkpoints)) {
            return self::$checkpoints[$id];
        }

        return null;
    }

    public static function reset(Player $player)
    {
        ChatController::message(onlinePlayers(), '_info', $player->group, ' ', $player, ' resets checkpoints.');
        self::clearCheckpoints();
    }

    public static function playerCheckpoint(Player $player, $score, $lap, $cpId)
    {
        $checkpoint = self::getCheckpoint($cpId);

        if ($checkpoint && !$checkpoint->givenTimeIsBetter($score)) {
            return;
        }

        self::$checkpoints[$cpId] = new Checkpoint($player, $score, $cpId);

        self::showCheckpointRecords(false, $cpId);
    }

    public static function playerConnect()
    {
        self::showCheckpointRecords();
    }

    public static function showCheckpointRecords(bool $clear = false, $cpId = null)
    {
        $cps = new Collection();

        if (!$clear) {
            $columns = config('cp-records.columns');

            foreach (self::$checkpoints as $checkpoint) {
                $row = floor(($checkpoint->id) / $columns);
                $y = $row * 10.5 - 10;
                $posInRow = $checkpoint->id % $columns;
                $x = $posInRow * 110.5 - (110.5 * $columns / 2);

                if (isset($cpId) && $cpId == $checkpoint->id) {
                    $cps->push(Template::toString('cp-records.cp-record',
                        ['x' => $x, 'y' => -$y, 'cp' => $checkpoint, 'flash' => uniqid()]));
                } else {
                    $cps->push(Template::toString('cp-records.cp-record',
                        ['x' => $x, 'y' => -$y, 'cp' => $checkpoint]));
                }
            }
        }

        $manialink = sprintf(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><manialink id="CPRecords" version="3"><frame scale="0.3" pos="' . config('cp-records.pos') . '">%s</frame></manialink>',
            $cps->implode('')
        );

        \esc\Classes\Server::sendDisplayManialinkPage('', $manialink);
    }
}
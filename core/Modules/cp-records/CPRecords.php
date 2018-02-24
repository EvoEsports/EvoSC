<?php

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;
use Illuminate\Support\Collection;

class CPRecords
{
    private static $checkpoints;

    public function __construct()
    {
        include_once 'Checkpoint.php';

        self::clearCheckpoints();

        Template::add('cpr.record', File::get(__DIR__ . '/Templates/cp-record.latte.xml'));

        Hook::add('EndMatch', 'CPRecords::clearCheckpoints');
        Hook::add('PlayerCheckpoint', 'CPRecords::playerCheckpoint');
        Hook::add('PlayerConnect', 'CPRecords::playerConnect');
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

    public static function playerCheckpoint(Player $player, int $score, int $lap, int $cpId)
    {
        $checkpoint = self::getCheckpoint($cpId);

        if ($checkpoint && !$checkpoint->givenTimeIsBetter($score)) {
            return;
        }

        self::$checkpoints[$cpId] = new Checkpoint($player, $score, $cpId);

        self::showCheckpointRecords();
    }

    public static function playerConnect()
    {
        self::showCheckpointRecords();
    }

    public static function showCheckpointRecords(bool $clear = false)
    {
        $cps = new Collection();

        if (!$clear) {
            $columns = config('cpr.columns');

            foreach (self::$checkpoints as $checkpoint) {
                $row = floor(($checkpoint->id) / $columns);
                $y = $row * 10.5 - 10;
                $posInRow = $checkpoint->id % $columns;
                $x = $posInRow * 110.5 - (110.5 * $columns / 2);
                $cps->push(Template::toString('cpr.record', ['x' => $x, 'y' => -$y, 'cp' => $checkpoint]));
            }
        }

        $manialink = sprintf(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><manialink id="CPRecords" version="3"><frame scale="0.35" pos="0 85.5">%s</frame></manialink>',
            $cps->implode('')
        );

        \esc\Classes\Server::getRpc()->sendDisplayManialinkPage('', $manialink);
    }
}
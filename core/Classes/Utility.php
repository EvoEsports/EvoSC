<?php


namespace EvoSC\Classes;


use EvoSC\Controllers\MapController;
use EvoSC\Models\Player;

class Utility
{
    /**
     * Calculates the range for the bottom ranks of a score table widget (locals/dedis)
     *
     * @param $baseRank
     * @param $showTop
     * @param $showTotal
     * @param $total
     * @return array
     */
    public static function getRankRange(int $baseRank, int $showTop, int $showTotal, int $total)
    {
        if ($total <= $showTop) {
            return [$showTop + 1, $total];
        }

        $showBottom = $showTotal - $showTop;
        $fillTop = floor($showBottom / 2);
        $fillBottom = $fillTop;

        $start = $baseRank - $fillTop;
        $end = $baseRank + $fillBottom;

        while ($start <= $showTop) {
            $start++;
            $end++;
        }

        while (($end - $start + $showTop) < $showTotal) {
            $end++;
        }

        if ($end > $total) {
            $diff = $end - $total;
            $start -= $diff;
            $end -= $diff;
        }

        while ($start <= $showTop) {
            $start++;
        }

        return [$start, $end];
    }

    /**
     * @param string $table
     * @param int $mapId
     * @param int $score
     * @return int
     */
    public static function getNextBetterRank(string $table, int $mapId, int $score)
    {
        $data = DB::table($table)
            ->select('Rank')
            ->where('Map', '=', $mapId)
            ->where('Score', '<=', $score)
            ->orderByDesc('Rank')
            ->first();

        if ($data) {
            return $data->Rank + 1;
        }

        return 1;
    }

    /**
     * @param string $table
     * @param int $mapId
     * @param int $deleteAbove
     */
    public static function fixRanks(string $table, int $mapId, int $deleteAbove = 100)
    {
        DB::raw('SET @rank=0');
        DB::raw('UPDATE `' . $table . '` SET `Rank`= @rank:=(@rank+1) WHERE `Map` = ' . $mapId . ' ORDER BY `Score`');
        DB::table($table)->where('Map', '=', $mapId)->where('Rank', '>', $deleteAbove)->delete();
    }

    /**
     * @param string $table
     * @param string $configId
     * @param string $templateId
     * @param Player|null $playerIn
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendRecordsChunk(string $table, string $configId, string $templateId, Player $playerIn = null)
    {
        if (!$map = MapController::getCurrentMap()) {
            return;
        }

        if (!$playerIn) {
            $players = onlinePlayers();
        } else {
            $players = collect([$playerIn]);
        }

        $count = DB::table($table)->where('Map', '=', $map->id)->count();
        $top = config($configId . '.show-top', 3);
        $fill = config($configId . '.rows', 16);

        if ($count <= $fill) {
            $recordsJson = DB::table($table)
                ->selectRaw('`Rank` as `rank`, `' . $table . '`.Score as score, NickName as name, Login as login, "[]" as cps')
                ->leftJoin('players', 'players.id', '=', $table . '.Player')
                ->where('Map', '=', $map->id)
                ->where('Rank', '<=', $fill)
                ->orderBy('rank')
                ->get()
                ->toJson();

            Template::showAll($templateId, compact('recordsJson'));
            return;
        }

        $playerRanks = DB::table($table)
            ->select(['Player', 'Rank'])
            ->where('Map', '=', $map->id)
            ->whereIn('Player', $players->pluck('id'))
            ->pluck('Rank', 'Player');

        $defaultRecordsJson = null;
        $defaultTopView = null;

        foreach ($players as $player) {
            $recordsJson = null;

            if ($playerRanks->has($player->id)) {
                $baseRank = (int)$playerRanks->get($player->id);
            } else {
                if (!is_null($defaultRecordsJson)) {
                    Template::show($player, $templateId, ['recordsJson' => $defaultRecordsJson], true, 20);
                    continue;
                }
                $baseRank = $count;
            }

            if ($baseRank <= $fill) {
                if (is_null($defaultTopView)) {
                    $defaultTopView = DB::table($table)
                        ->selectRaw('`Rank` as `rank`, `' . $table . '`.Score as score, NickName as name, Login as login, "[]" as cps')
                        ->leftJoin('players', 'players.id', '=', $table . '.Player')
                        ->where('Map', '=', $map->id)
                        ->WhereBetween('Rank', [$count - $fill + $top, $count])
                        ->orWhere('Map', '=', $map->id)
                        ->where('Rank', '<=', $top)
                        ->orderBy('rank')
                        ->get()
                        ->toJson();
                }
                $recordsJson = $defaultTopView;
            }

            if (!isset($recordsJson)) {
                $range = Utility::getRankRange($baseRank, $top, $fill, $count);

                $recordsJson = DB::table($table)
                    ->selectRaw('`Rank` as `rank`, `' . $table . '`.Score as score, NickName as name, Login as login, "[]" as cps')
                    ->leftJoin('players', 'players.id', '=', $table . '.Player')
                    ->where('Map', '=', $map->id)
                    ->WhereBetween('Rank', $range)
                    ->orWhere('Map', '=', $map->id)
                    ->where('Rank', '<=', $top)
                    ->orderBy('rank')
                    ->get()
                    ->toJson();
            }

            if ($baseRank == $count) {
                $defaultRecordsJson = $recordsJson;
            }

            Template::show($player, $templateId, compact('recordsJson'), true, 20);
        }

        Template::executeMulticall();
    }
}
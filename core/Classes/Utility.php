<?php


namespace EvoSC\Classes;


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
}
<?php


namespace esc\Classes;


class Utlity
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
    public static function getRankRange($baseRank, $showTop, $showTotal, $total)
    {
        if ($total <= $showTop) {
            return [4, $total];
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
}
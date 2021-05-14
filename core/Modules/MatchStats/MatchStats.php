<?php


namespace EvoSC\Modules\MatchStats;


use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\MatchSettingsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;
use League\Csv\Writer;

class MatchStats extends Module implements ModuleInterface
{
    private static string $matchDir;
    private static string $mapFileName;
    private static Collection $roundStats;

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        self::$roundStats = collect();

        Hook::add('Maniaplanet.StartRound_Start', [self::class, 'roundStart']);
        Hook::add('Maniaplanet.EndRound_End', [self::class, 'roundEnd']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);

        if (config('match-stats.log-chat', true)) {
            Hook::add('ChatLine', [self::class, 'newChatLine']);
        }

        $matchDate = date('Y-m-d_H-i', time());
        $matchSettings = basename(MatchSettingsController::getCurrentMatchSettingsFile());
        self::$matchDir = cacheDir("match-stats/$matchSettings/$matchDate");
    }

    /**
     * @param Player $player
     * @param int $time
     * @param string $checkpoints
     */
    public static function playerFinish(Player $player, int $time, string $checkpoints)
    {
        self::$roundStats->push((object)[
            'player'      => $player,
            'score'       => $time == 0 ? 'DNF' : formatScore($time),
            'checkpoints' => $checkpoints
        ]);
    }

    /**
     * @param $data
     */
    public static function roundStart($data)
    {
        self::$roundStats = collect();
        self::$mapFileName = str_replace(DIRECTORY_SEPARATOR, '_', MapController::getCurrentMap()->filename);
    }

    /**
     * @param $data
     * @throws \League\Csv\CannotInsertRecord
     * @throws \League\Csv\InvalidArgument
     */
    public static function roundEnd($data)
    {
        if (self::$roundStats->isEmpty()) {
            return;
        }

        $teamInfo = [Server::getTeamInfo(0), Server::getTeamInfo(1)];
        $roundNumber = json_decode($data[0])->count;
        $targetFile = self::$matchDir . '/' . self::$mapFileName . "/rounds/$roundNumber.csv";

        $writer = Writer::createFromPath($targetFile, 'w+');
        $writer->setDelimiter(';');
        $writer->setEnclosure('"');
        $writer->insertOne([
            'position',
            'name_plain',
            'score',
            'team',
            'login',
            'name',
            'checkpoints'
        ]);

        $scores = self::$roundStats->where('score', '!=', 'DNF')
            ->merge(self::$roundStats->where('score', '=', 'DNF'))
            ->values()
            ->map(function ($score, $pos) use ($teamInfo) {
            /**
             * @var Player $player
             */
            $player = $score->player;

            return [
                $pos + 1,
                stripAll($player->NickName),
                $score->score,
                $teamInfo[$player->team]->name ?: ['Blue', 'Red'][$player->team],
                $player->Login,
                $player->NickName,
                $score->checkpoints
            ];
        })->toArray();

        $writer->insertAll($scores);
    }

    /**
     * @param string $line
     */
    public static function newChatLine(string $line)
    {
        $targetFile = self::$matchDir . '/' . self::$mapFileName . "/chatlog.txt";
        File::appendLine($targetFile, date('[Y-m-d H:i:s] ', time()) . stripAll($line));
    }
}
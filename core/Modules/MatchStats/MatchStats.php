<?php


namespace EvoSC\Modules\MatchStats;


use Carbon\Carbon;
use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\MatchSettingsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;
use League\Csv\Reader;
use League\Csv\Writer;

class MatchStats extends Module implements ModuleInterface
{
    private static string $matchDir;
    private static string $mapFileName;
    private static Collection $roundStats;
    private static array $teamPoints;
    private static Writer $teamPointsWriter;
    private static Carbon $mapStart;
    private static int $mapId;

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('Maniaplanet.StartRound_Start', [self::class, 'roundStart']);
        Hook::add('Maniaplanet.EndRound_End', [self::class, 'roundEnd']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('Scores', [self::class, 'scoresUpdated']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMap', [self::class, 'endMap']);

        if (config('match-stats.log-chat', true)) {
            Hook::add('ChatLine', [self::class, 'newChatLine']);
        }

        $matchDate = date('Y-m-d_H-i', time());
        $matchSettings = basename(MatchSettingsController::getCurrentMatchSettingsFile());
        self::$matchDir = cacheDir("match-stats/$matchSettings/$matchDate");
        self::$mapId = 1;
        self::roundStart();
    }

    /**
     * @param Map $map
     * @throws \Exception
     */
    public static function beginMap(Map $map)
    {
        self::$mapFileName = str_replace(DIRECTORY_SEPARATOR, '_', $map->filename);
        self::$mapStart = now();
    }

    /**
     * @param Map $map
     * @throws \League\Csv\CannotInsertRecord
     */
    public static function endMap(Map $map)
    {
        $targetFile = self::$matchDir . '/' . self::$mapFileName . "/team_points.csv";

        if (File::exists($targetFile)) {
            $teamPointsWriter = Writer::createFromPath(getOsSafePath($targetFile), 'w+');
            $teamPointsReader = Reader::createFromPath(getOsSafePath($targetFile), 'r+');
            foreach ($teamPointsReader->getIterator() as $item) {
                $teamPointsWriter->insertOne($item);
            }
        } else {
            File::put($targetFile, '');
            $teamPointsWriter = Writer::createFromPath(getOsSafePath($targetFile), 'w+');
            $teamPointsWriter->setDelimiter(';');
            $teamPointsWriter->setEnclosure('"');
            $teamPointsWriter->insertOne(['map', 'name', 'filename', 'team1', 'team2', 'start', 'end']);
        }

        $teamPointsWriter->insertOne([
            self::$mapId++,
            stripAll($map->name),
            $map->filename,
            self::$teamPoints[0],
            self::$teamPoints[1],
            self::$mapStart->toDateTimeString(),
            now()->toDateTimeString(),
        ]);
    }

    /**
     * @param $scores
     */
    public static function scoresUpdated(\stdClass $scores)
    {
        self::$teamPoints = [
            $scores->teams[0]->mappoints,
            $scores->teams[1]->mappoints
        ];
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
    public static function roundStart($data = null)
    {
        self::$teamPoints = [0, 0];
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
                    $player->team,
                    $player->Login,
                    $player->NickName,
                    $score->checkpoints
                ];
            })->toArray();

        File::put($targetFile, '');
        $writer = Writer::createFromPath(getOsSafePath($targetFile), 'w+');
        $writer->setDelimiter(';');
        $writer->setEnclosure('"');
        $writer->insertOne(['position', 'name_plain', 'score', 'team', 'team_id', 'login', 'name', 'checkpoints']);
        $writer->insertAll($scores);
    }

    /**
     * @param string $line
     */
    public static function newChatLine(string $line)
    {
        if (config('match-stats.log-chat-per-map', false)) {
            $targetFile = self::$matchDir . "/chat.txt";
        } else {
            $targetFile = self::$matchDir . '/' . self::$mapFileName . "/chat.txt";
        }

        File::appendLine($targetFile, date('[Y-m-d H:i:s] ', time()) . stripAll($line));
    }
}
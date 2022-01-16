<?php


namespace EvoSC\Modules\MatchStats;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\File;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;
use League\Csv\Writer;

class MatchStats extends Module implements ModuleInterface
{
    const CACHE_DIR = 'match-stats';

    private static ?string $activeMatch = null;
    private static Collection $roundStats;
    private static array $teamPoints = [];
    private static bool $isWarmUpOngoing = false;

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Log::warning("Match Statistics module has a known memory leak, use with caution.");

        self::$roundStats = collect();
        self::$teamPoints = [];

        AccessRight::add('record_match_stats', 'Is allowed to control match stats recording.');

        Hook::add('Maniaplanet.StartRound_Start', [self::class, 'roundStart']);
        Hook::add('Maniaplanet.EndRound_End', [self::class, 'roundEnd']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
        Hook::add('Scores', [self::class, 'scoresUpdated']);
        Hook::add('EndMap', [self::class, 'updateResult']);
        Hook::add('Trackmania.WarmUp.StartRound', [self::class, 'warmupStart']);
        Hook::add('Trackmania.WarmUp.EndRound', [self::class, 'warmupEnd']);

        ManiaLinkEvent::add('ms.start_recording', [self::class, 'startRecording'], 'record_match_stats');
        ManiaLinkEvent::add('ms.stop_recording', [self::class, 'stopRecording'], 'record_match_stats');
        ManiaLinkEvent::add('ms.download', [self::class, 'downloadStats'], 'record_match_stats');

        ChatCommand::add('//stats', [self::class, 'sendWidget'], 'Match stats recording.', 'record_match_stats');
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendWidget(Player $player)
    {
        $matchNumber = File::getDirectoryContents(cacheDir(self::CACHE_DIR))->count() + 1;
        $currentMatch = self::$activeMatch;

        Template::show($player, 'MatchStats.update', compact('currentMatch'));
        Template::show($player, 'MatchStats.widget', compact('matchNumber'));
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showRecordedMatches(Player $player)
    {
        $matches = File::getDirectoryContents(cacheDir(self::CACHE_DIR))
            ->map(function ($dir) {
                return (object)[
                    'dir'  => $dir,
                    'date' => date("dS M Y H:i", filemtime(cacheDir(self::CACHE_DIR . '/' . $dir)))
                ];
            });

        Template::show($player, 'MatchStats.matches', compact('matches'));
    }

    /**
     * @param Player $player
     * @param string $matchName
     * @throws \EvoSC\Exceptions\FilePathNotAbsoluteException
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function startRecording(Player $player, string $matchName)
    {
        self::$activeMatch = $matchName;
        $target = self::getTargetDirectory();

        if (is_null($target)) {
            dangerMessage('Failed to start recording ', secondary(' INVALID DIRECTORY'))
                ->send($player);

            return;
        }

        if (!File::dirExists($target)) {
            File::makeDir($target);
        }

        Template::show($player, 'MatchStats.update', ['currentMatch' => $matchName]);

        successMessage($player, ' started stats tracking for match ', secondary($matchName))
            ->setIcon('')
            ->sendAll();
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function stopRecording(Player $player)
    {
        if (File::getDirectoryContents(self::getTargetDirectory())->isEmpty()) {
            File::delete(self::getTargetDirectory());
        }

        dangerMessage($player, ' stops stats tracking for match ', secondary(self::$activeMatch))
            ->setIcon('')
            ->sendAll();

        self::$activeMatch = null;
        Template::show($player, 'MatchStats.update', ['currentMatch' => '']);
    }

    /**
     * @param Map|null $map
     * @throws \League\Csv\CannotInsertRecord
     * @throws \League\Csv\InvalidArgument
     */
    public static function updateResult(Map $map = null)
    {
        $targetDir = self::getTargetDirectory();

        if (is_null($targetDir)) {
            return;
        }

        $targetFile = "$targetDir/result.csv";

        File::put($targetFile, '');
        $teamPointsWriter = Writer::createFromPath(getOsSafePath($targetFile), 'w+');
        $teamPointsWriter->setDelimiter(';');
        $teamPointsWriter->setEnclosure('"');
        $teamPointsWriter->insertOne(['map', 'team1', 'team2']);

        foreach (self::$teamPoints as $mapName => $points) {
            $teamPointsWriter->insertOne([
                $mapName,
                $points[0],
                $points[1]
            ]);
        }
    }

    /**
     * @param $scores
     */
    public static function scoresUpdated(\stdClass $scores)
    {
        $mapName = Server::getCurrentMapInfo()->name;
        self::$teamPoints[$mapName] = [
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
        if (self::$isWarmUpOngoing) {
            return;
        }

        self::$roundStats->push((object)[
            'player'      => $player,
            'score'       => $time == 0 ? 'DNF' : formatScore($time),
            'checkpoints' => $checkpoints
        ]);
    }

    /**
     *
     */
    public static function warmupStart()
    {
        self::$isWarmUpOngoing = true;
    }

    /**
     *
     */
    public static function warmupEnd()
    {
        self::$isWarmUpOngoing = false;
    }

    /**
     * @param $data
     */
    public static function roundStart($data = null)
    {
        $mapName = Server::getCurrentMapInfo()->name;
        self::$teamPoints[$mapName] = [0, 0];
        self::$roundStats = collect();
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
        $targetDir = self::getTargetDirectory();
        $mapSlug = evo_str_slug(Server::getCurrentMapInfo()->name);
        $targetFile = "$targetDir/$mapSlug/$roundNumber.csv";

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

        self::updateResult();
    }

    /**
     * @param Player $player
     * @param string $dir
     */
    public static function downloadStats(Player $player, string $dir)
    {

    }

    /**
     * @return string|null
     */
    private static function getTargetDirectory(): ?string
    {
        if (is_null(self::$activeMatch)) {
            return null;
        }

        $slug = evo_str_slug(self::$activeMatch);
        return cacheDir(self::CACHE_DIR . '/' . $slug);
    }
}
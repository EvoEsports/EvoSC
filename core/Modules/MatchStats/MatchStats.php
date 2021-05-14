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
        Hook::add('ChatLine', [self::class, 'newChatLine']);

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
     * @param string $line
     */
    public static function newChatLine(string $line)
    {
        $targetFile = self::$matchDir . '/' . self::$mapFileName . "/chatlog.txt";
        File::appendLine($targetFile, date('[Y-m-d H:i:s] ', time()) . stripAll($line));
    }

    /**
     * @param ...$data
     */
    public static function roundStart(...$data)
    {
        self::$roundStats = collect();
        self::$mapFileName = str_replace(DIRECTORY_SEPARATOR, '_', MapController::getCurrentMap()->filename);
    }

    /**
     * @param ...$data
     */
    public static function roundEnd(...$data)
    {
        if (self::$roundStats->isEmpty()) {
            return;
        }

        $teamInfo = [Server::getTeamInfo(0), Server::getTeamInfo(1)];
        $roundNumber = json_decode($data[0][0])->count;

        $targetFile = self::$matchDir . '/' . self::$mapFileName . "/rounds/$roundNumber.csv";
        File::put($targetFile, 'position;name_plain;team;score;login;name;checkpoints');

        $dnfs = self::$roundStats->where('score', '=', 'DNF');
        $scores = self::$roundStats->where('score', '!=', 'DNF')->merge($dnfs)->values();

        foreach ($scores as $i => $score) {
            /**
             * @var Player $player
             */
            $player = $score->player;

            $line = sprintf('%d;%s;%s;%s;%s;%s;%s;',
                $i + 1,
                stripAll($player->NickName),
                $teamInfo[$player->team]->name ?: ['Blue', 'Red'][$player->team],
                $score->score,
                $player->Login,
                $player->NickName,
                $score->checkpoints);

            File::appendLine($targetFile, "$line");
        }
    }
}
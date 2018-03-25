<?php

require_once __DIR__ . '/DedimaniaApi.php';

use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;

class Dedimania extends DedimaniaApi
{
    private static $maxRank = 30;

    private static $dedis;

    public function __construct()
    {
        $this->createTables();
        self::$dedis = new Collection();

        include_once __DIR__ . '/Models/Dedi.php';
        include_once __DIR__ . '/Models/DedimaniaSession.php';

        Hook::add('BeginMap', 'Dedimania::beginMap');
        Hook::add('EndMatch', 'Dedimania::endMatch');
        Hook::add('PlayerConnect', 'Dedimania::displayDedis');
        Hook::add('PlayerFinish', 'Dedimania::playerFinish');
        Hook::add('PlayerCheckpoint', 'Dedimania::playerCheckpoint');

        Template::add('dedis', File::get(__DIR__ . '/Templates/dedis.latte.xml'));

        ManiaLinkEvent::add('dedis.show', 'Dedimania::showDedisModal');
    }

    private function createTables()
    {
        Database::create('dedi-records', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('Map');
            $table->integer('Player');
            $table->integer('Score');
            $table->integer('Rank');
            $table->unique(['Map', 'Rank']);
        });

        Database::create('dedi-sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Session');
            $table->boolean('Expired')->default(false);
            $table->timestamps();
        });
    }

    public static function beginMap(Map $map)
    {
        self::$newTimes = new Collection();
        self::$checkpoints = new Collection();
        $session = self::getSession();

        if ($session == null) {
            Log::warning("Dedimania offline. Using cached values.");
        }

        $data = self::getChallengeRecords($map);

        if (isset($data->params->param->value->struct->member[3]->value->array->data->value)) {
            $records = $data->params->param->value->struct->member[3]->value->array->data->value;

            foreach ($records as $record) {
                try {
                    $login = (string)$record->struct->member[0]->value->string;
                    $nickname = (string)$record->struct->member[1]->value->string;
                    $score = (int)$record->struct->member[2]->value->int;
                    $rank = (int)$record->struct->member[3]->value->int;

                    $player = Player::firstOrCreate(['Login' => $login]);
                } catch (\Exception $e) {
                    continue;
                }

                if (isset($player->id)) {
                    $player->update(['NickName' => $nickname]);

                    Dedi::whereMap($map->id)->whereRank($rank)->delete();

                    Dedi::create([
                        'Map' => $map->id,
                        'Player' => $player->id,
                        'Score' => $score,
                        'Rank' => $rank
                    ]);
                }
            }
        } else {
            if (isset($data->fault)) {
                $message = $data->fault->value->struct->member[1]->value->string;

                if (preg_match('/SessionId .+ not found !!!/', $message)) {
                    Log::logAddLine('Dedimania', 'Session expired, generating new.');
                    $session->update(['Expired' => true]);
                    self::beginMap($map);
                    return;
                } else {
                    Log::logAddLine('! Dedimania !', $message);
                }
            }
        }

        //Remove faulty dedis
        Dedi::where('Map', $map->id)->where('Score', 0)->delete();
        while (true) {
            $last = $map->dedis()->orderByDesc('Score')->get()->first();
            $foreLast = $map->dedis()->orderByDesc('Score')->skip(1)->take(1)->first();

            if($foreLast->Player == $last->Player){
                $last->delete();
            }else{
                break;
            }
        }

        foreach (onlinePlayers() as $player) {
            self::displayDedis($player);
        }
    }

    public static function endMatch($sPlayerRanking, int $winnerTeam)
    {
        $map = MapController::getCurrentMap();
        self::setChallengeTimes($map);
    }

    public static function playerCheckpoint(Player $player, int $time, int $curLap, int $cpId)
    {
        $cp = collect([]);
        $cp->player = $player;
        $cp->time = $time;
        $cp->id = $cpId;

        $existingCpTime = self::$checkpoints->where('player.Login', $player->Login)->where('id', $cpId);
        if ($existingCpTime->isNotEmpty()) {
            self::$checkpoints = self::$checkpoints->diff($existingCpTime);
        }

        self::$checkpoints->push($cp);
    }

    public static function addNewTime(Dedi $dedi)
    {
        $existingDedi = self::$newTimes->where('Player', $dedi->Player);

        if ($existingDedi->isNotEmpty()) {
            if (isset($existingDedi->ghostReplayFile) && file_exists($existingDedi->ghostReplayFile)) {
                unlink($existingDedi->ghostReplayFile);
            }

            self::$newTimes = self::$newTimes->diff($existingDedi);
        }

        $ghostFile = sprintf('%s_%s_%d', stripAll($dedi->player->Login), stripAll($dedi->map->Name), $dedi->Score);

        try {
            $saved = Server::saveBestGhostsReplay($dedi->player->Login, 'Ghosts/' . $ghostFile);
        } catch (\Exception $e) {
            Log::error('Could not save ghost: ' . $e->getMessage());
        }

        if (isset($saved) && !$saved) {
            Log::error('Saving top 1 dedi failed');
            return;
        } else {
            $dedi->ghostReplayFile = $ghostFile;
        }

        self::$newTimes->push($dedi);

        foreach (onlinePlayers() as $player) {
            self::displayDedis($player);
        }
    }

    /**
     * Checks if player has a dedi
     * @param Map $map
     * @param Player $player
     * @return bool
     */
    public static function playerHasDedi(Map $map, Player $player): bool
    {
        return $map->dedis()->where('Player', $player->id)->get()->isNotEmpty();
    }

    /**
     * called on playerFinish
     * @param Player $player
     * @param int $score
     */
    public static function playerFinish(Player $player, int $score)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $map = MapController::getCurrentMap();

        $dedisCount = $map->dedis()->count();

        if (self::playerHasDedi($map, $player)) {
            $dedi = $map->dedis()->wherePlayer($player->id)->first();

            if ($score == $dedi->Score) {
                ChatController::messageAll('Player ', $player, ' equaled his/hers ', $dedi);
                return;
            }

            if ($score < $dedi->Score) {
                $diff = $dedi->Score - $score;
                $rank = self::getRank($map, $score);

                if ($rank < self::$maxRank) {
                    return;
                }

                if ($rank != $dedi->Rank) {
                    $dedi->update(['Score' => $score]);
                    $dedi = self::fixDedimaniaRanks($map, $player);
                    ChatController::messageAll('Player ', $player, ' gained the ', $dedi, ' (-' . formatScore($diff) . ')');
                    self::addNewTime($dedi);
                } else {
                    $dedi->update(['Score' => $score]);
                    ChatController::messageAll('Player ', $player, ' secured his/hers ', $dedi, ' (-' . formatScore($diff) . ')');
                    self::addNewTime($dedi);
                }
            }
        } else {
            if ($dedisCount < 100) {
                self::pushDedi($map, $player, $score, 999);
                $dedi = self::fixDedimaniaRanks($map, $player);
                ChatController::messageAll('Player ', $player, ' made the ', $dedi);
                self::addNewTime($dedi);
            }
        }
    }

    /**
     * Inser the dedi
     * @param Map $map
     * @param Player $player
     * @param int $score
     * @param int $rank
     * @return Dedi
     */
    private static function pushDedi(Map $map, Player $player, int $score, int $rank): Dedi
    {
        $map->dedis()->create([
            'Player' => $player->id,
            'Map' => $map->id,
            'Score' => $score,
            'Rank' => $rank,
        ]);

        self::fixDedimaniaRanks($map, $player);

        return $map->dedis()->where('Player', $player->id)->first();
    }

    private static function fixDedimaniaRanks(Map $map, Player $player = null): ?Dedi
    {
        $dedis = $map->dedis()->orderBy('Score');
        $i = 1;
        foreach ($dedis as $dedi) {
            $dedi->update(['Rank' => $i]);
            $i++;
        }

        if ($player) {
            return $map->dedis()->wherePlayer($player->id)->first();
        }

        return null;
    }

    /**
     * Get insert position
     * @param Map $map
     * @param int $score
     * @return int|null
     */
    private static function getRank(Map $map, int $score): ?int
    {
        $nextBetter = $map->dedis->where('Score', '<=', $score)->sortByDesc('Score')->first();

        if ($nextBetter) {
            return $nextBetter->Rank + 1;
        }

        return 1;
    }

    /**
     * Push down worse ranks
     * @param Map $map
     * @param int $startRank
     */
    private static function pushDownRanks(Map $map, int $startRank)
    {
        $map->dedis()->where('Rank', '>=', $startRank)->orderByDesc('Rank')->increment('Rank');
    }

    /**
     * Display all dedis in window
     * @param Player $player
     */
    public static function showDedisModal(Player $player)
    {
        $map = MapController::getCurrentMap();
        $chunks = $map->dedis->chunk(25);

        $columns = [];
        foreach ($chunks as $key => $chunk) {
            $ranking = Template::toString('esc.ranking', ['ranks' => $chunk]);
            $ranking = '<frame pos="' . ($key * 45) . ' 0" scale="0.8">' . $ranking . '</frame>';
            array_push($columns, $ranking);
        }

        Template::show($player, 'esc.modal', [
            'id' => 'DediRecordsOverview',
            'width' => 180,
            'height' => 97,
            'content' => implode('', $columns ?? [])
        ]);
    }

    /**
     * Show the dedi widget
     * @param Player|null $player
     */
    public static function displayDedis(Player $player = null)
    {
        $rows = config('ui.dedis.rows');
        $map = MapController::getCurrentMap();
        $dedis = new Collection();

        $topDedis = $map->dedis()->orderBy('Score')->take(3)->get();
        $topPlayers = $topDedis->pluck('Player')->toArray();
        $fill = $rows - $topDedis->count();

        if ($player && !in_array($player->id, $topPlayers)) {
            $dedi = $map->dedis()->where('Player', $player->id)->first();
            if ($dedi) {
                $halfFill = ceil($fill / 2);

                $above = $map->dedis()
                    ->where('Score', '<=', $dedi->Score)
                    ->whereNotIn('Player', $topPlayers)
                    ->orderByDesc('Score')
                    ->take($halfFill)
                    ->get();

                $bottomFill = $fill - $above->count();

                $below = $map->dedis()
                    ->where('Score', '>', $dedi->Score)
                    ->whereNotIn('Player', $topPlayers)
                    ->orderBy('Score')
                    ->take($bottomFill)
                    ->get();

                $dedis = $dedis->concat($above)->concat($below);
            } else {
                $dedis = $map->dedis()->whereNotIn('Player', $topPlayers)->orderByDesc('Score')->take($fill)->get();
            }
        } else {
            $dedis = $map->dedis()->whereNotIn('Player', $topPlayers)->orderByDesc('Score')->take($fill)->get();
        }

        $result = $dedis->concat($topDedis)->sortBy('Score');

        $variables = [
            'id' => 'Dedimania',
            'title' => '🏆  DEDIMANIA',
            'x' => config('ui.dedis.x'),
            'y' => config('ui.dedis.y'),
            'rows' => $rows,
            'scale' => config('ui.dedis.scale'),
            'content' => Template::toString('dedis', ['dedis' => $result]),
            'action' => 'dedis.show'
        ];

        if ($player) {
            Template::show($player, 'esc.box', $variables);
        } else {
            Template::showAll('esc.box', $variables);
        }
    }

    /**
     * @return int
     */
    public static function getMaxRank(): int
    {
        return self::$maxRank;
    }

    /**
     * @param int $maxRank
     */
    public static function setMaxRank(int $maxRank)
    {
        self::$maxRank = $maxRank;
    }
}
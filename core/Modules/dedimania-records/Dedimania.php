<?php

namespace esc\Modules\Dedimania;

require_once __DIR__ . '/DedimaniaApi.php';

use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\Timer;
use esc\Controllers\ChatController;
use esc\Controllers\KeyController;
use esc\Controllers\MapController;
use esc\Controllers\TemplateController;
use esc\Models\Dedi;
use esc\Models\LocalRecord;
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
        self::$dedis = collect();

        Hook::add('BeginMap', [Dedimania::class, 'beginMap']);
        Hook::add('EndMatch', [Dedimania::class, 'endMatch']);
        Hook::add('PlayerConnect', [Dedimania::class, 'displayDedis']);
        Hook::add('PlayerConnect', [DedimaniaApi::class, 'playerConnect']);
        Hook::add('PlayerFinish', [Dedimania::class, 'playerFinish']);

        ManiaLinkEvent::add('dedis.show', [Dedimania::class, 'showDedisModal']);

        ChatController::addCommand('maxrank', [Dedimania::class, 'printMaxRank'], 'Show from which rank dedis get saved');
        ChatController::addCommand('dedicps', [Dedimania::class, 'printDediCps'], 'SPrints cps for given dedi to chat');

        Timer::create('dedimania.players.update', [Dedimania::class, 'reportConnectedPlayersToDedimania'], '4m');

        KeyController::createBind('Y', [self::class, 'reload']);
    }

    public static function reportConnectedPlayersToDedimania()
    {
        $map  = MapController::getCurrentMap();
        $data = self::updateServerPlayers($map);

        if ($data && !isset($data->params->param->value->boolean)) {
            Log::logAddLine('[!] Dedimania', 'Failed to report connected players.');
            var_dump($data);
        }

        Timer::create('dedimania.players.update', [Dedimania::class, 'reportConnectedPlayersToDedimania'], '4m');
    }

    public static function printDediCps(Player $player, $cmd = null, $dediId = null)
    {
        if (!$dediId) {
            return;
        }

        $map  = MapController::getCurrentMap();
        $dedi = $map->dedis()->where('Rank', $dediId)->first();

        if ($dedi && $dedi->Checkpoints) {
            $output = "";
            foreach (explode(",", $dedi->Checkpoints) as $id => $time) {
                $output .= "$id: " . formatScore(intval($time)) . ", ";
            }
            ChatController::message($player, $output);
        } else {
            ChatController::message($player, 'No checkpoints for this dedi');
        }
    }

    public static function printMaxRank(Player $player, ...$args)
    {
        ChatController::message($player, 'Dedimania is unlocked up to rank ', $player->MaxRank ?? self::getMaxRank());
    }

    public static function beginMap(Map $map)
    {
        self::$newTimes = collect();
        $session        = self::getSession();

        if ($session == null) {
            Log::warning("Dedimania offline. Using cached values.");
        }

        $data = self::getChallengeRecords($map);

        if (isset($data->params->param->value->struct->member[3]->value->array->data->value)) {
            $records = $data->params->param->value->struct->member[3]->value->array->data->value;
            self::setMaxRank((int)$data->params->param->value->struct->member[1]->value->int);

            $map->dedis()->delete();

            foreach ($records as $record) {
                try {

                    $login       = (string)$record->struct->member[0]->value->string;
                    $nickname    = (string)$record->struct->member[1]->value->string;
                    $score       = (int)$record->struct->member[2]->value->int;
                    $rank        = (int)$record->struct->member[3]->value->int;
                    $checkpoints = (string)$record->struct->member[5]->value->string;

                    $player = Player::firstOrCreate(['Login' => $login]);
                } catch (\Exception $e) {
                    continue;
                }

                if (isset($player->id)) {
                    $player->update(['NickName' => $nickname]);

                    Dedi::create([
                        'Map'         => $map->id,
                        'Player'      => $player->id,
                        'Score'       => $score,
                        'Rank'        => $rank,
                        'Checkpoints' => $checkpoints
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

                    return;
                }
            }
        }

        self::fixDedimaniaRanks($map);

        //Remove faulty dedis
        $map->dedis()->where('Score', 0)->delete();
        while (true) {
            $last     = $map->dedis()->orderByDesc('Score')->first();
            $foreLast = $map->dedis()->orderByDesc('Score')->skip(1)->take(1)->first();

            if (!$foreLast || !$last) {
                break;
            }

            if ($foreLast->Player == $last->Player) {
                $last->delete();
            } else {
                break;
            }
        }

        foreach (onlinePlayers() as $player) {
            self::displayDedis($player);
        }
    }

    public static function endMatch()
    {
        $map = MapController::getCurrentMap();
        self::setChallengeTimes($map);
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
    }

    public static function pushNewDediToPlayers(Dedi $record, $oldRank = null)
    {
        $nick         = str_replace('\\', "\\\\", str_replace('"', "''", $record->player->NickName));
        $updateRecord = sprintf('["rank" => "%d", "cps" => "%s", "score" => "%s", "score_raw" => "%s", "nick" => "%s", "login" => "%s", "oldRank" => "%d"]', $record->Rank, $record->Checkpoints, formatScore($record->Score), $record->Score, $nick, $record->player->Login, $oldRank);

        Template::showAll('dedimania-records.update', compact('updateRecord', 'oldRank'));
    }

    /**
     * called on playerFinish
     *
     * @param Player $player
     * @param int $score
     */
    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score < 8000) {
            //ignore times under 8 seconds
            return;
        }

        Log::logAddLine('Dedimania', 'Player ' . $player->Login . ' finished with time ' . formatScore($score), false);

        $map = MapController::getCurrentMap();

        $dedi = $map->dedis()->wherePlayer($player->id)->first();

        if ($dedi) {
            //Player has dedi on map

            if ($score == $dedi->Score) {
                ChatController::messageAll('_dedi', 'Player ', $player, ' equaled his/her ', $dedi);

                return;
            }

            $oldRank = $dedi->Rank;

            if ($score < $dedi->Score) {
                $diff = $dedi->Score - $score;
                $dedi->update(['Score' => $score, 'Checkpoints' => $checkpoints, 'New' => 1]);
                $dedi = self::fixDedimaniaRanks($map, $player);

                if ($dedi->Rank <= (isset($player->MaxRank) ? $player->MaxRank : self::$maxRank)) {
                    if ($oldRank == $dedi->Rank) {
                        ChatController::messageAll('_dedi', 'Player ', $player, ' secured his/her ', $dedi,
                            ' (-' . formatScore($diff) . ')');
                    } else {
                        ChatController::messageAll('_dedi', 'Player ', $player, ' gained the ', $dedi,
                            ' (-' . formatScore($diff) . ')');
                    }
                    self::addNewTime($dedi);
                    self::pushNewDediToPlayers($dedi, $oldRank);
                }
            }
        } else {
            //Player does not have a dedi on map

            $map->dedis()->create([
                'Player'      => $player->id,
                'Map'         => $map->id,
                'Score'       => $score,
                'Rank'        => 999,
                'Checkpoints' => $checkpoints,
            ]);

            $dedi = self::fixDedimaniaRanks($map, $player);

            if ($dedi->Rank <= (isset($player->MaxRank) ? $player->MaxRank : self::$maxRank)) {
                self::addNewTime($dedi);
                self::pushNewDediToPlayers($dedi);
                ChatController::messageAll('_dedi', 'Player ', $player, ' gained the ', $dedi);
            }
        }
    }

    private static function fixDedimaniaRanks(Map $map, Player $player = null): ?Dedi
    {
        $dedis = $map->dedis()->orderBy('Score')->get();
        $i     = 1;
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
     * Display all dedis in window
     *
     * @param Player $player
     */
    public static function showDedisModal(Player $player)
    {
        $map    = MapController::getCurrentMap();
        $chunks = $map->dedis()->orderBy('Score')->get()->chunk(25);

        $columns = [];
        foreach ($chunks as $key => $chunk) {
            $ranking = Template::toString('components.ranking', ['ranks' => $chunk]);
            $ranking = '<frame pos="' . ($key * 45) . ' 0" scale="0.8">' . $ranking . '</frame>';
            array_push($columns, $ranking);
        }

        if ($map->mx_world_record != null) {
            $mxScore = intval($map->mx_world_record->ReplayTime);
            if ($mxScore > $chunks->first()->first()->Score) {
                $chunks->first()->first()->Rank = 'WR';
            }
        }

        Template::show($player, 'components.modal', [
            'id'            => 'DediRecordsOverview',
            'width'         => 180,
            'height'        => 97,
            'content'       => implode('', $columns ?? []),
            'showAnimation' => true,
        ]);
    }

    public static function reload(Player $player)
    {
        TemplateController::loadTemplates();
        self::displayDedis($player);
    }

    /**
     * Show the dedi widget
     *
     * @param Player|null $player
     */
    public static function displayDedis(Player $player = null)
    {
        if ($player) {
            $map      = MapController::getCurrentMap();
            $allDedis = $map->dedis->sortBy('Rank');

            $playerRecord = $map->dedis()->wherePlayer($player->id)->first();

            if (!$playerRecord) {
                //Fallback to local if no dedi
                $record = $map->locals()->wherePlayer($player->id)->first();

                if ($record) {
                    $localCps  = explode(',', $record->Checkpoints);
                    array_walk($localCps, function (&$time) {
                        $time = intval($time);
                    });

                    $localRank = -1;
                } else {
                    $localRank = -1;
                    $localCps  = [];
                }
            } else {
                $localRank = $playerRecord->Rank;
                $localCps  = explode(',', $playerRecord->Checkpoints);
                array_walk($localCps, function (&$time) {
                    $time = intval($time);
                });
            }

            $cpCount       = (int)$map->gbx->CheckpointsPerLaps;
            $onlinePlayers = onlinePlayers()->pluck('Login');

            $records = '[' . $allDedis->map(function (Dedi $dedi) {
                    $nick = str_replace('\\', "\\\\", str_replace('"', "''", $dedi->player->NickName));

                    return sprintf('%d => ["cps" => "%s", "score" => "%s", "score_raw" => "%s", "nick" => "%s", "login" => "%s"]', $dedi->Rank, $dedi->Checkpoints, formatScore($dedi->Score), $dedi->Score, $nick, $dedi->player->Login);
                })->implode(",\n") . ']';

            Template::show($player, 'dedimania-records.manialink', compact('records', 'localRank', 'localCps', 'cpCount', 'onlinePlayers'));
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
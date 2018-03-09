<?php

require_once __DIR__ . '/DedimaniaApi.php';

use esc\classes\Database;
use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\classes\Template;
use esc\controllers\ChatController;
use esc\controllers\MapController;
use esc\models\Map;
use esc\models\Player;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;

class Dedimania extends DedimaniaApi
{
    private static $dedis;

    public function __construct()
    {
        $this->createTables();
        self::$dedis = new Collection();

        include_once __DIR__ . '/Models/Dedi.php';
        include_once __DIR__ . '/Models/DedimaniaSession.php';

        Hook::add('BeginMap', 'Dedimania::beginMap');
        Hook::add('PlayerConnect', 'Dedimania::displayDedis');

        Template::add('dedis', File::get(__DIR__ . '/Templates/dedis.latte.xml'));

        ManiaLinkEvent::add('dedis.show', 'Dedimania::showDedisModal');
    }

    private function createTables()
    {
        Database::create('dedi-records', function (Blueprint $table) {
            $table->integer('Map');
            $table->integer('Player');
            $table->integer('Score');
            $table->integer('Rank');
            $table->primary(['Map', 'Rank']);
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
        $session = self::getSession();

        if ($session == null) {
            Log::warning("Dedimania offline. Using cached values.");
        }

        $data = self::getChallengeRecords($map);
        $records = $data->params->param->value->struct->member[3]->value->array->data->value;

        foreach ($records as $record) {
            try{
                $login = (string)$record->struct->member[0]->value->string;
                $nickname = (string)$record->struct->member[1]->value->string;
                $score = (int)$record->struct->member[2]->value->int;
                $rank = (int)$record->struct->member[3]->value->int;

                $player = Player::firstOrCreate(['Login' => $login]);
            }catch(\Exception $e){
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

        Dedi::where('Map', $map->id)->where('Score', 0)->delete();

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
        return $map->dedis->where('Player', $player->id)->isNotEmpty();
    }

    /**
     * called on playerFinish
     * @param Player $player
     * @param int $score
     */
    public static function playerFinish(Player $player, int $score)
    {
        if ($score == 0) {
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

                if ($rank != $dedi->Rank) {
                    self::pushDownRanks($map, $rank);
                    $dedi->update(['Score' => $score, 'Rank' => $rank]);
                    ChatController::messageAll('Player ', $player, ' gained the ', $dedi, ' (-' . formatScore($diff) . ')', ' [!! VALUES NOT SAVED TO DEDIMANIA !!]');
                } else {
                    $dedi->update(['Score' => $score]);
                    ChatController::messageAll('Player ', $player, ' improved his/hers ', $dedi, ' (-' . formatScore($diff) . ')', ' [!! VALUES NOT SAVED TO DEDIMANIA !!]');
                }
            }
        } else {
            if ($dedisCount < 100) {
                $worstDedi = $map->dedis()->orderByDesc('Score')->first();

                if ($worstDedi) {
                    if ($score <= $worstDedi->Score) {
                        self::pushDownRanks($map, $worstDedi->Rank);
                        $dedi = self::pushDedi($map, $player, $score, $worstDedi->Rank);
                        ChatController::messageAll('Player ', $player, ' gained the ', $dedi, ' [!! VALUES NOT SAVED TO DEDIMANIA !!]');
                    } else {
                        $dedi = self::pushDedi($map, $player, $score, $worstDedi->Rank + 1);
                        ChatController::messageAll('Player ', $player, ' made the ', $dedi, ' [!! VALUES NOT SAVED TO DEDIMANIA !!]');
                    }
                } else {
                    $rank = 1;
                    $dedi = self::pushDedi($map, $player, $score, $rank);
                    ChatController::messageAll('Player ', $player, ' made the ', $dedi, ' [!! VALUES NOT SAVED TO DEDIMANIA !!]');
                }
            }
        }

        self::displayDedis();
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

        return $map->dedis()->whereRank($rank)->first();
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
}
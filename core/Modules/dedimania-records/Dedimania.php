<?php

use esc\classes\Config;
use esc\classes\Database;
use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\classes\RestClient;
use esc\classes\Template;
use esc\controllers\ChatController;
use esc\controllers\MapController;
use esc\classes\Server;
use esc\models\Map;
use esc\models\Player;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;

class Dedimania
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

    private static function getSession(): ?DedimaniaSession
    {
        $session = DedimaniaSession::whereExpired(false)->orderByDesc('updated_at')->first();

        if ($session) {
            Log::info("Dedimania using stored session: $session->Session from $session->created_at");
        } else {
            $sessionId = self::authenticateAndValidateAccount();

            if ($sessionId == null) {
                Log::warning("Connection to Dedimania failed. Using cached values.");
                return null;
            }

            Log::info("Dedimania session created: $sessionId");

            return DedimaniaSession::create(['Session' => $sessionId]);
        }

        return $session;
    }

    public static function beginMap(Map $map)
    {
        $session = self::getSession();

        if ($session == null) {
            Log::warning("Dedimania offline. Using cached values.");
//            ChatController::messageAll('Dedimania is offline. Using cached values.');
        }

        $data = self::call('dedimania.GetChallengeInfo', [
            'UId' => $map->UId
        ]);

        $records = $data->params->param->value->struct->member[5]->value->array->data->value;

        foreach ($records as $record) {
            $login = (string)$record->struct->member[0]->value->string;
            $nickname = (string)$record->struct->member[1]->value->string;
            $score = (int)$record->struct->member[2]->value->int;
            $rank = (int)$record->struct->member[3]->value->int;

            $player = Player::firstOrCreate(['Login' => $login]);

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

        foreach (Dedi::whereMap($map->id)->orderBy('Score')->get() as $key => $dedi) {
            $dedi->update(['Rank' => $key + 1]);
        }

        foreach (onlinePlayers() as $player) {
            self::displayDedis($player);
        }
    }

    public static function playerHasDedi(Map $map, Player $player): bool
    {
        return $map->dedis->where('Player', $player->id)->isNotEmpty();
    }

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

    private static function getRank(Map $map, int $score): ?int
    {
        $nextBetter = $map->dedis->where('Score', '<=', $score)->sortByDesc('Score')->first();

        if ($nextBetter) {
            return $nextBetter->Rank + 1;
        }

        return 1;
    }

    private static function pushDownRanks(Map $map, int $startRank)
    {
        $map->dedis()->where('Rank', '>=', $startRank)->orderByDesc('Rank')->increment('Rank');
    }

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
     * Connect to dedimania and authenticate
     */
    private static function authenticateAndValidateAccount(): ?string
    {
        $response = Dedimania::callStruct('dedimania.OpenSession', [
            'Game' => 'TM2',
            'Login' => Config::get('dedimania.login'),
            'Code' => Config::get('dedimania.key'),
            'Tool' => 'EvoSC',
            'Version' => getEscVersion(),
            'Packmask' => 'Stadium',
            'ServerVersion' => Server::getRpc()->getVersion()->version,
            'ServerBuild' => Server::getRpc()->getVersion()->build,
            'Path' => Server::getRpc()->getDetailedPlayerInfo(Config::get('dedimania.login'))->path
        ]);

        try {
            return (string)$response->params->param->value->array->data->value->array->data->value->struct->member[0]->value->string;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Call a method on dedimania server
     * See documentation at http://dedimania.net:8081/Dedimania
     * @param string $method
     * @param array|null $parameters
     * @return null|SimpleXMLElement
     */
    public static function call(string $method, array $parameters = null): ?SimpleXMLElement
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', $method);

        $params = $xml->addChild('params');

        if ($parameters) {
            foreach ($parameters as $key => $param) {
                $member = $params->addChild('member');
                $member->addChild('name', $key);
                $member->addChild('value')->addChild('string', $param);
            }
        }

        $response = RestClient::post('http://dedimania.net:8081/Dedimania', [
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF8'
            ],
            'body' => $xml->asXML()
        ]);

        if ($response->getStatusCode() != 200) {
            Log::error($response->getReasonPhrase());
            return null;
        }

        return new SimpleXMLElement($response->getBody());
    }

    /**
     * Call a method on dedimania server
     * See documentation at http://dedimania.net:8081/Dedimania
     * @param string $method
     * @param array|null $parameters
     * @return null|SimpleXMLElement
     */
    public static function callStruct(string $method, array $parameters = null): ?SimpleXMLElement
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'system.multicall');

        $struct = $xml
            ->addChild('params')
            ->addChild('param')
            ->addChild('value')
            ->addChild('array')
            ->addChild('data')
            ->addChild('value')
            ->addChild('struct');

        $member = $struct->addChild('member');
        $member->addChild('name', 'methodName');
        $member->addChild('value')->addChild('string', $method);

        if ($parameters) {
            $structArrayMember = $struct->addChild('member');
            $structArrayMember->addChild('name', 'params');
            $structArray = $structArrayMember->addChild('value')->addChild('array')->addChild('data')->addChild('value')->addChild('struct');

            foreach ($parameters as $key => $param) {
                $subMember = $structArray->addChild('member');
                $subMember->addChild('name', $key);
                $subMember->addChild('value')->addChild('string', $param);
            }
        }

        $response = RestClient::post('http://dedimania.net:8081/Dedimania', [
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF8'
            ],
            'body' => $xml->asXML()
        ]);

        if ($response->getStatusCode() != 200) {
            Log::error($response->getReasonPhrase());
            return null;
        }

        $content = $response->getBody()->getContents();

        try {
            return new SimpleXMLElement($content);
        } catch (\Exception $e) {
            Log::error("Could not parse content to XML");
            return null;
        }
    }
}
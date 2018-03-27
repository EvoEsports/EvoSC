<?php

include_once __DIR__ . '/MXK.php';
include_once __DIR__ . '/Models/Karma.php';

use esc\Classes\Database;
use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\HookController;
use esc\Models\Map;
use esc\Models\Player;
use GuzzleHttp\Client;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;

class MxKarma extends MXK
{
    private static $session;
    private static $apiKey;
    private static $client;
    private static $currentMap;
    private static $mapKarma;

    private static $ratings;
    private static $updatedVotes;

    public function __construct()
    {
        MxKarma::setApiKey(config('mxk.key'));

        Template::add('mx-karma', File::get(__DIR__ . '/Templates/mx-karma.latte.xml'));

        $client = new Client([
            'base_uri' => 'https://karma.mania-exchange.com/api2/'
        ]);

        MxKarma::setClient($client);

        MxKarma::startSession();

        self::$updatedVotes = new Collection();
        self::$ratings = [0 => 'Trash', 20 => 'Bad', 40 => 'Playable', 60 => 'Ok', 80 => 'Good', 100 => 'Fantastic'];

        Hook::add('PlayerConnect', 'MxKarma::showWidget');
        Hook::add('PlayerFinish', 'MxKarma::playerFinish');
        Hook::add('BeginMap', 'MxKarma::beginMap');
        Hook::add('EndMap', 'MxKarma::endMap');

        ChatController::addCommand('+', 'MxKarma::votePlus', 'Rate the map ok', '');
        ChatController::addCommand('++', 'MxKarma::votePlusPlus', 'Rate the map good', '');
        ChatController::addCommand('+++', 'MxKarma::votePlusPlusPlus', 'Rate the map fantastic', '');
        ChatController::addCommand('-', 'MxKarma::voteMinus', 'Rate the map playable', '');
        ChatController::addCommand('--', 'MxKarma::voteMinusMinus', 'Rate the map bad', '');
        ChatController::addCommand('---', 'MxKarma::voteMinusMinusMinus', 'Rate the map trash', '');

        \esc\Classes\ManiaLinkEvent::add('mxk.vote', 'MxKarma::vote');

        MxKarma::createTables();
    }

    public static function createTables()
    {
        Database::create('mx-karma', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('Player');
            $table->integer('Map');
            $table->integer('rating');
            $table->unique(['Player', 'Map']);
        });
    }

    /* +++ 100, ++ 80, + 60, - 40, -- 20, - 0*/
    public static function vote(Player $player, int $rating)
    {
        if (!$player) {
            Log::warning("Null player tries to vote");
            return;
        }

        if (!self::playerFinished($player)) {
            //Prevent players from voting when they didnt finish
            return;
        }

        $map = \esc\Controllers\MapController::getCurrentMap();

        Log::info(stripAll($player->NickName) . " rated " . stripAll($map->Name) . " @ $rating");

        $karma = $map->ratings()->wherePlayer($player->Login)->first();

        if ($karma) {
            if ($karma->rating == $rating) {
                return;
            }

            $karma->update(['rating' => $rating]);
        } else {
            $karma = Karma::insert([
                'Player' => $player->id,
                'Map' => $map->id,
                'rating' => $rating
            ]);
        }

        if (isset($karma)) {
            HookController::call('PlayerRateMap', [$player, $karma]);
        }

        self::$updatedVotes->push($player->id);
        self::$updatedVotes = self::$updatedVotes->unique();

        ChatController::messageAll($player, ' rated this track ', secondary(self::$ratings[$rating]));

        self::showWidget($player);
    }

    /**
     * @param Player $player
     */
    public static function votePlus(Player $player)
    {
        self::vote($player, 60);
    }

    /**
     * @param Player $player
     */
    public static function votePlusPlus(Player $player)
    {
        self::vote($player, 80);
    }

    /**
     * @param Player $player
     */
    public static function votePlusPlusPlus(Player $player)
    {
        self::vote($player, 100);
    }

    /**
     * @param Player $player
     */
    public static function voteMinus(Player $player)
    {
        self::vote($player, 40);
    }

    /**
     * @param Player $player
     */
    public static function voteMinusMinus(Player $player)
    {
        self::vote($player, 20);
    }

    /**
     * @param Player $player
     */
    public static function voteMinusMinusMinus(Player $player)
    {
        self::vote($player, 0);
    }

    /**
     * Called on endMap
     * @param Map $map
     */
    public static function endMap(Map $map = null)
    {
        if ($map) {
            $votes = [];

            $ratings = $map->ratings()->whereIn('Player', self::$updatedVotes->toArray());

            foreach ($ratings as $rating) {
                if (!$rating->player) {
                    var_dump($rating);
                    continue;
                }

                array_push($votes, [
                    'login' => $rating->player->Login,
                    'nickname' => $rating->player->NickName,
                    'vote' => $rating->rating,
                ]);
            }

            if (count($votes) == 0) {
                return;
            }

            $response = self::call(MXK::saveVotes, $map, $votes);

            if ($response instanceof stdClass && !$response->updated) {
                Log::warning('Could not update MX Karma.');
            }
        }
    }

    /**
     * @return mixed
     */
    public static function getApiKey()
    {
        return self::$apiKey;
    }

    /**
     * @param mixed $apiKey
     */
    public static function setApiKey($apiKey)
    {
        self::$apiKey = $apiKey;
    }

    /**
     * @return mixed
     */
    public static function getSession(): stdClass
    {
        return self::$session;
    }

    /**
     * @param String $session
     */
    public static function setSession($session)
    {
        self::$session = $session;
    }

    /**
     * @return mixed
     */
    public static function getClient(): Client
    {
        return self::$client;
    }

    /**
     * @param mixed $client
     */
    public static function setClient($client)
    {
        self::$client = $client;
    }

    /**
     * Called on beginMap
     */
    public static function beginMap()
    {
        self::$updatedVotes = new Collection();

        foreach (onlinePlayers() as $player) {
            self::showWidget($player);
        }
    }

    public static function getUpdatedVotesAverage()
    {
        $map = \esc\Controllers\MapController::getCurrentMap();
        $items = new Collection();

        for ($i = 0; $i < self::$mapKarma->votecount ?? 0; $i++) {
            $items->push(self::$mapKarma->voteaverage);
        }

        $ratings = $map->whereIn('Player', self::$updatedVotes->toArray());

        foreach ($ratings as $rating) {
            $items->push($rating->rating);
        }

        return $items->average();
    }

    /**
     * Unlock voting if player finished
     * @param Player $player
     * @param int $score
     */
    public static function playerFinish(Player $player, int $score)
    {
        if ($score > 0) {
            self::showWidget($player);
        }
    }

    /**
     * Display the widget
     */
    public static function showWidget(Player $player)
    {
        $mapUid = Server::getCurrentMapInfo()->uId;

        if (self::$currentMap != $mapUid) {
            self::$mapKarma = self::call(MXK::getMapRating);
            self::$currentMap = $mapUid;
        }

        $average = self::getUpdatedVotesAverage();

        $starString = '';
        $stars = $average / 20;
        $full = floor($stars);
        $left = $stars - $full;

        for ($i = 0; $i < $full; $i++) {
            $starString .= '';
        }

        if ($left >= 0.5) {
            $starString .= '';
            $full++;
        }

        for ($i = $full; $i < 5; $i++) {
            $starString .= '';
        }

        Template::show($player, 'esc.box', [
            'id' => 'MXKarma',
            'title' => '  MX KARMA',
            'x' => config('ui.mx-karma.x'),
            'y' => config('ui.mx-karma.y'),
            'rows' => 1.5,
            'scale' => config('ui.mx-karma.scale'),
            'content' => Template::toString('mx-karma', ['karma' => self::$mapKarma, 'average' => $average, 'stars' => $starString, 'finished' => self::playerFinished($player)])
        ]);
    }

    public static function playerFinished(Player $player): bool
    {
        $map = \esc\Controllers\MapController::getCurrentMap();

        if ($player->Score > 0) {
            return true;
        }

        if ($map->ratings()->wherePlayer($player->id)->first() != null) {
            return true;
        }

        if ($map->locals()->wherePlayer($player->id)->first() != null) {
            return true;
        }

        if ($map->dedis()->wherePlayer($player->id)->first() != null) {
            return true;
        }

        return false;
    }

    /**
     * Call MX Karma method
     * @param int $method
     * @param Map|null $map
     * @param array|null $votes
     * @return null|stdClass
     */
    public static function call(int $method, Map $map = null, array $votes = null): ?stdClass
    {
        switch ($method) {
            case MXK::startSession:
                $requestMethod = 'GET';

                $query = [
                    'serverLogin' => config('server.login'),
                    'applicationIdentifier' => 'ESC v' . getEscVersion(),
                    'testMode' => 'false'
                ];

                $function = 'startSession';
                break;

            case MXK::activateSession:
                $requestMethod = 'GET';

                $query = [
                    'sessionKey' => self::getSession()->sessionKey,
                    'activationHash' => hash("sha512", (self::$apiKey . self::getSession()->sessionSeed))
                ];

                $function = 'activateSession';
                break;

            case MXK::getMapRating:
                $requestMethod = 'POST';

                $query = [
                    'sessionKey' => self::getSession()->sessionKey
                ];

                $json = [
                    'gamemode' => self::getGameMode(),
                    'titleid' => Server::getVersion()->titleId,
                    'mapuid' => Server::getCurrentMapInfo()->uId,
                    'getvotesonly' => 'false',
                    'playerlogins' => []
                ];

                $function = 'getMapRating';
                break;

            case MXK::saveVotes:
                if (count(self::$updatedVotes) == 0) {
                    return null;
                }

                $requestMethod = 'POST';

                $query = [
                    'sessionKey' => self::getSession()->sessionKey
                ];

                $json = [
                    'gamemode' => self::getGameMode(),
                    'titleid' => Server::getVersion()->titleId,
                    'mapuid' => $map->UId,
                    'mapname' => $map->Name,
                    'mapauthor' => $map->Author,
                    'isimport' => 'false',
                    'maptime' => config('server.roundTime') * 60,
                    'votes' => $votes
                ];

                $function = 'saveVotes';
                break;

            default:
                \esc\Classes\Log::error('Invalid MX Record method called.');
                return null;
        }

        //Do the request to mx servers
        $response = self::getClient()->request($requestMethod, $function, [
            'query' => $query ?? null,
            'json' => $json ?? null
        ]);

        //Check if request was successful
        if ($response->getStatusCode() != 200) {
            Log::warning('Connection to MX failed: ' . $response->getReasonPhrase());
            return null;
        }

        $responseBody = $response->getBody();
        $mxResponse = json_decode($responseBody);

        //Check if method was executed properly
        if (!$mxResponse->success) {
            Log::error('MX Karma method execution failed ' . $requestMethod . '(' . $function . '): ' . $mxResponse->data->message);
            return null;
        }

        return $mxResponse->data;
    }

    /**
     * Returns current game mode string
     * @return string
     */
    private static function getGameMode(): string
    {
        $gameMode = Server::getGameMode();

        switch ($gameMode) {
            case 0:
                return Server::getScriptName()['CurrentValue'];
            case 1:
                return "Rounds";
            case 2:
                return "TimeAttack";
            case 3:
                return "Team";
            case 4:
                return "Laps";
            case 5:
                return "Cup";
            case 6:
                return "Stunts";
        }
    }

    /**
     * Starts MX Karma session
     */
    public static function startSession()
    {
        Log::info("Starting MX Karma session...");

        $auth = self::call(MXK::startSession);

        if ($auth) {
            self::setSession($auth);

            $mxResponse = self::call(MXK::activateSession);

            if (!$mxResponse->activated || !isset($mxResponse->activated)) {
                Log::warning('Could not activate session @ MX Karma.');
                return;
            }
        } else {
            Log::warning('Could not authenticate @ MX Karma.');
            return;
        }

        Log::info("MX Karma session created.");
    }
}
<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\MXK;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Karma;
use esc\Models\Map;
use esc\Models\Player;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use stdClass;

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
        if (!config('mx-karma.enabled')) {
            return;
        }

        MxKarma::setApiKey(config('mx-karma.key'));

        $client = new Client([
            'base_uri' => 'https://karma.mania-exchange.com/api2/',
        ]);

        MxKarma::setClient($client);

        MxKarma::startSession();

        self::$updatedVotes = collect([]);
        self::$ratings      = [0 => 'Trash', 20 => 'Bad', 40 => 'Playable', 60 => 'Ok', 80 => 'Good', 100 => 'Fantastic'];

        Hook::add('PlayerConnect', [MxKarma::class, 'showWidget']);
        Hook::add('PlayerFinish', [MxKarma::class, 'playerFinish']);
        Hook::add('BeginMap', [MxKarma::class, 'beginMap']);
        Hook::add('EndMap', [MxKarma::class, 'endMap']);

        ChatController::addCommand('+', [MxKarma::class, 'votePlus'], 'Rate the map ok', '');
        ChatController::addCommand('++', [MxKarma::class, 'votePlusPlus'], 'Rate the map good', '');
        ChatController::addCommand('+++', [MxKarma::class, 'votePlusPlusPlus'], 'Rate the map fantastic', '');
        ChatController::addCommand('-', [MxKarma::class, 'voteMinus'], 'Rate the map playable', '');
        ChatController::addCommand('--', [MxKarma::class, 'voteMinusMinus'], 'Rate the map bad', '');
        ChatController::addCommand('---', [MxKarma::class, 'voteMinusMinusMinus'], 'Rate the map trash', '');
        ChatController::addCommand('-----', [MxKarma::class, 'voteMinusMinusMinusU'], 'Rate the map trash', '');

        \esc\Classes\ManiaLinkEvent::add('mxk.vote', [MxKarma::class, 'vote']);
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
            ChatController::message($player, 'You need to finish the track before you can vote.');

            return;
        }

        $map = \esc\Controllers\MapController::getCurrentMap();

        $karma = $map->ratings()
                     ->wherePlayer($player->id)
                     ->get()
                     ->first();

        if ($karma != null) {
            if ($karma->Rating == $rating) {
                //Prevent spam
                return;
            }

            $karma->update(['Rating' => $rating]);
        } else {
            $karma = Karma::create([
                'Player' => $player->id,
                'Map'    => $map->id,
                'Rating' => $rating,
            ]);
        }

        self::$updatedVotes->push($player->id);

        ChatController::message(onlinePlayers(), '_info', $player, ' rated this map ', secondary(strtolower(self::$ratings[$rating])));
        Log::info($player . " rated " . $map . " @ $rating|" . self::$ratings[$rating]);

        foreach (onlinePlayers() as $player) {
            self::showWidget($player);
        }
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
     * @param Player $player
     */
    public static function voteMinusMinusMinusU(Player $player)
    {
        if (!self::playerFinished($player)) {
            //Prevent players from voting when they didnt finish
            ChatController::message($player, 'You need to finish the track before you can vote.');

            return;
        }

        $map = \esc\Controllers\MapController::getCurrentMap();

        $karma = $map->ratings()
                     ->wherePlayer($player->id)
                     ->get()
                     ->first();

        if ($karma != null) {
            if ($karma->Rating == 0) {
                //Prevent spam
                return;
            }

            $karma->update(['Rating' => 0]);
        } else {
            $karma = Karma::create([
                'Player' => $player->id,
                'Map'    => $map->id,
                'Rating' => 0,
            ]);
        }

        self::$updatedVotes->push($player->id);

        ChatController::message(onlinePlayers(), '_info', $player, ' rated this map ', secondary('the worst a human being ever had to play'), '.');
    }

    /**
     * @param \esc\Models\Map|null $map
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function endMap(Map $map = null)
    {
        return; //Disabled until error @ mx is resolved

        if (self::$updatedVotes->isEmpty()) {
            //No new votes
            return;
        }

        if ($map) {
            $votes = [];

            $ratings = $map->ratings()->whereIn('Player', self::$updatedVotes->toArray())->get();

            Log::logAddLine('MxKarma', $ratings->count() . ' new map ratings:', isVerbose());
            Log::logAddLine('MxKarma', $ratings->toJson(), isVeryVerbose());

            foreach ($ratings as $rating) {
                if (!$rating->Player) {
                    Log::logAddLine('MxKarma', 'Invalid rating encountered.', isVerbose());
                    Log::logAddLine('MxKarma', $rating->toJson(), isVeryVerbose());
                    continue;
                }

                $player = Player::whereId($rating->Player)->first();

                if (!$player) {
                    continue;
                }

                array_push($votes, [
                    'login'    => $player->Login,
                    'nickname' => $player->NickName,
                    'vote'     => $rating->Rating,
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
        $map = MapController::getCurrentMap();

        if (!$map) {
            return 0.0;
        }

        $items = collect([]);

        /* Disabled until error @ mx is resolved
        for ($i = 0; $i < self::$mapKarma->votecount; $i++) {
            $items->push(self::$mapKarma->voteaverage);
        }
        */

        $newRatings = $map->ratings()
                          ->get();

        foreach ($newRatings as $rating) {
            $items->push($rating->Rating);
        }

        return $items->average();
    }

    /**
     * Unlock voting if player finished
     *
     * @param Player $player
     * @param int    $score
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
        $map    = MapController::getCurrentMap();
        $mapUid = $map->uid;

        /*
        if (self::$currentMap != $mapUid) {
            self::$mapKarma   = self::call(MXK::getMapRating);
            self::$currentMap = $mapUid;
        }
        */

        $average = self::getUpdatedVotesAverage();

        $starString = '';
        $stars      = $average / 20;
        $full       = floor($stars);
        $left       = $stars - $full;

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

        $finished = self::playerFinished($player);
        $myRating = $map->ratings()->where('Player', $player->id)->first();
        $myRating = $myRating ? $myRating->Rating : null;
        Template::show($player, 'mx-karma.mx-karma', compact('starString', 'finished', 'myRating'));
    }

    public static function playerFinished(Player $player): bool
    {
        $map = \esc\Controllers\MapController::getCurrentMap();

        if (!$map) {
            return false;
        }

        if ($player->Score > 0) {
            return true;
        }

        if ($map->ratings()
                ->wherePlayer($player->id)
                ->first() != null) {
            return true;
        }

        if ($map->locals()
                ->wherePlayer($player->id)
                ->first() != null) {
            return true;
        }

        if ($map->dedis()
                ->wherePlayer($player->id)
                ->first() != null) {
            return true;
        }

        return false;
    }

    /**
     * Call MX Karma method
     *
     * @param int        $method
     * @param Map|null   $map
     * @param array|null $votes
     *
     * @return null|stdClass
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function call(int $method, Map $map = null, array $votes = null): ?stdClass
    {
        switch ($method) {
            case MXK::startSession:
                $requestMethod = 'GET';

                $query = [
                    'serverLogin'           => config('server.login'),
                    'applicationIdentifier' => 'ESC v' . getEscVersion(),
                    'testMode'              => 'false',
                ];

                $function = 'startSession';
                break;

            case MXK::activateSession:
                $requestMethod = 'GET';

                $query = [
                    'sessionKey'     => self::getSession()->sessionKey,
                    'activationHash' => hash("sha512", (self::$apiKey . self::getSession()->sessionSeed)),
                ];

                $function = 'activateSession';
                break;

            case MXK::getMapRating:
                $requestMethod = 'POST';

                $query = [
                    'sessionKey' => self::getSession()->sessionKey,
                ];

                $json = [
                    'gamemode'     => self::getGameMode(),
                    'titleid'      => Server::getVersion()->titleId,
                    'mapuid'       => Server::getCurrentMapInfo()->uId,
                    'getvotesonly' => 'false',
                    'playerlogins' => [],
                ];

                $function = 'getMapRating';
                break;

            case MXK::saveVotes:
                if (count(self::$updatedVotes) == 0) {
                    return null;
                }

                $requestMethod = 'POST';

                $query = [
                    'sessionKey' => self::getSession()->sessionKey,
                ];

                $json = [
                    'gamemode'  => self::getGameMode(),
                    'titleid'   => Server::getVersion()->titleId,
                    'mapuid'    => $map->Uid,
                    'mapname'   => $map->Name,
                    'mapauthor' => $map->Author,
                    'isimport'  => 'false',
                    'maptime'   => MapController::getTimeLimit() * 60,
                    'votes'     => $votes,
                ];

                $function = 'saveVotes';
                break;

            default:
                \esc\Classes\Log::error('Invalid MX Record method called.');

                return null;
        }

        //Do the request to mx servers
        $response = self::getClient()
                        ->request($requestMethod, $function, [
                            'query' => $query ?? null,
                            'json'  => $json ?? null,
                        ]);

        //Check if request was successful
        if ($response->getStatusCode() != 200) {
            Log::warning('Connection to MX failed: ' . $response->getReasonPhrase());

            return null;
        }

        $responseBody = $response->getBody();
        $mxResponse   = json_decode($responseBody);

        //Check if method was executed properly
        if (!$mxResponse->success) {
            Log::logAddLine('MxKarma', sprintf('%s->%s failed', $requestMethod, $function), isVerbose());
            Log::logAddLine('MxKarma', $responseBody, isVeryVerbose());

            return null;
        }

        return $mxResponse->data;
    }

    /**
     * Returns current game mode string
     *
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
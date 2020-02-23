<?php

namespace esc\Modules;

use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\ChatCommand;
use esc\Controllers\CountdownController;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Karma;
use esc\Models\Map;
use esc\Models\Player;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use stdClass;

class MxKarma implements ModuleInterface
{
    const startSession = 1;
    const activateSession = 2;
    const getMapRating = 3;
    const saveVotes = 4;

    private static $apiKey;
    private static $mapKarma;
    private static $updatedVotesAverage;

    /**
     * @var stdClass
     */
    private static $session;

    /**
     * @var Client
     */
    private static $client;

    /**
     * @var Map
     */
    private static $currentMap;

    /**
     * @var array
     */
    private static $ratings;

    /**
     * @var \Illuminate\Support\Collection
     */
    private static $updatedVotesPlayerIds;

    private static $offline = false;

    public function __construct()
    {
        if (!config('mx-karma.enabled')) {
            return;
        }

        self::$apiKey = config('mx-karma.key');
        self::$updatedVotesPlayerIds = collect([]);
        self::$ratings = [0 => 'Trash', 20 => 'Bad', 40 => 'Playable', 60 => 'Ok', 80 => 'Good', 100 => 'Fantastic'];
        self::$client = new Client([
            'base_uri' => 'https://karma.mania-exchange.com/api2/',
        ]);

        try {
            MxKarma::startSession();
        } catch (ConnectException $e) {
            Log::error($e->getMessage(), true);
            self::$offline = true;
            return;
        }

        Hook::add('PlayerConnect', [self::class, 'showWidget']);
        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMap', [self::class, 'endMap']);

        ChatCommand::add('+', [MxKarma::class, 'votePlus'], 'Rate the map ok', null, true);
        ChatCommand::add('++', [MxKarma::class, 'votePlusPlus'], 'Rate the map good', null, true);
        ChatCommand::add('+++', [MxKarma::class, 'votePlusPlusPlus'], 'Rate the map fantastic', null, true);
        ChatCommand::add('-', [MxKarma::class, 'voteMinus'], 'Rate the map playable', null, true);
        ChatCommand::add('--', [MxKarma::class, 'voteMinusMinus'], 'Rate the map bad', null, true);
        ChatCommand::add('---', [MxKarma::class, 'voteMinusMinusMinus'], 'Rate the map trash', null, true);
        ChatCommand::add('-----', [MxKarma::class, 'voteWorst'], 'Rate the map trash', null, true);

        ManiaLinkEvent::add('mxk.vote', [MxKarma::class, 'vote']);
    }

    /**
     * @param \esc\Models\Map|null $map
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function endMap(Map $map)
    {
        if (self::$updatedVotesPlayerIds->isEmpty()) {
            //No new votes
            return;
        }

        $ratings = $map->ratings()->whereIn('Player', self::$updatedVotesPlayerIds->toArray())->get();

        if ($ratings->count() == 0) {
            return;
        }

        Log::write($ratings->count() . ' new map ratings:', isVerbose());
        Log::write($ratings->toJson(), isVeryVerbose());

        $ratings->transform(function (Karma $rating) {
            return [
                'login' => $rating->player->Login,
                'nickname' => $rating->player->NickName,
                'vote' => $rating->Rating,
            ];
        });

        $response = self::call(self::saveVotes, $map, $ratings->toArray());

        if ($response instanceof stdClass && !$response->updated) {
            Log::warning('Could not update MX Karma.');
        }
    }

    public static function beginMap(Map $map)
    {
        self::$updatedVotesPlayerIds = collect();

        $mapUid = $map->uid;

        try {
            self::$mapKarma = self::call(self::getMapRating, $map);
        } catch (\Exception $e) {
            Log::error('Failed to get MxKarma ratings for ' . $map, isVerbose());
            self::$mapKarma = 50.0;
        }

        self::$currentMap = $mapUid;
        self::updateVotesAverage();
        self::sendUpdatedKarma();

        $onlinePlayers = onlinePlayers();
        $playerIds = $onlinePlayers->pluck('id');
        $ratings = DB::table('mx-karma')->where('Map', '=', $map->id)->whereIn('Player', $playerIds)
            ->select(['Rating', 'Player'])
            ->pluck('Rating', 'Player');

        foreach ($onlinePlayers as $player) {
            if ($ratings->has($player->id)) {
                $rating = $ratings->get($player->id);
            } else {
                $rating = self::playerCanVote($player, $map) ? -1 : -2; // -1 = can vote, -2 = can't vote
            }

            Template::show($player, 'mx-karma.update-my-vote', compact('rating'), true);
        }

        Template::executeMulticall();
    }

    public static function showWidget(Player $player)
    {
        $rating = self::$updatedVotesAverage;
        Template::show($player, 'mx-karma.mx-karma', compact('rating'));
    }

    public static function sendUpdatedKarma()
    {
        $map = MapController::getCurrentMap();

        if (!$map) {
            return;
        }

        $mapUid = $map->uid;

        if (self::$currentMap != $mapUid) {
            self::$mapKarma = self::call(self::getMapRating);
            self::$currentMap = $mapUid;
        }

        self::updateVotesAverage();

        $average = self::$updatedVotesAverage;
        Template::showAll('mx-karma.update-karma', compact('average'));
    }

    public static function playerCanVote(Player $player, Map $map): bool
    {
        if ($player->Score > 0) {
            return true;
        }

        if (DB::table('local-records')->where('Map', '=', $map->id)->where('Player', '=', $player->id)->exists()) {
            return true;
        }

        if (DB::table('mx-karma')->where('Map', '=', $map->id)->where('Player', '=', $player->id)->exists()) {
            return true;
        }

        if (DB::table('dedi-records')->where('Map', '=', $map->id)->where('Player', '=', $player->id)->exists()) {
            return true;
        }

        return false;
    }

    public static function updateVotesAverage()
    {
        $map = MapController::getCurrentMap();
        $items = collect([]);

        if ($map) {
            for ($i = 0; $i < self::$mapKarma->votecount; $i++) {
                $items->push(self::$mapKarma->voteaverage);
            }
        }

        $newRatings = $map->ratings()->get();

        foreach ($newRatings as $rating) {
            $items->push($rating->Rating);
        }

        self::$updatedVotesAverage = $items->average();
    }

    /**
     * Call MX Karma method
     *
     * @param int $method
     * @param Map|null $map
     * @param array|null $votes
     *
     * @return null|stdClass
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function call(int $method, Map $map = null, array $votes = null): ?stdClass
    {
        switch ($method) {
            case self::startSession:
                $requestMethod = 'GET';

                $query = [
                    'serverLogin' => config('server.login'),
                    'applicationIdentifier' => 'EvoSC v' . getEscVersion(),
                    'testMode' => 'false',
                ];

                $function = 'startSession';
                break;

            case self::activateSession:
                $requestMethod = 'GET';

                $query = [
                    'sessionKey' => self::$session->sessionKey,
                    'activationHash' => hash("sha512", (self::$apiKey . self::$session->sessionSeed)),
                ];

                $function = 'activateSession';
                break;

            case self::getMapRating:
                $requestMethod = 'POST';

                $query = [
                    'sessionKey' => self::$session->sessionKey,
                ];

                $json = [
                    'gamemode' => self::getGameMode(),
                    'titleid' => Server::getVersion()->titleId,
                    'mapuid' => $map->uid,
                    'getvotesonly' => 'false',
                    'playerlogins' => [],
                ];

                $function = 'getMapRating';
                break;

            case self::saveVotes:
                $requestMethod = 'POST';

                $query = [
                    'sessionKey' => self::$session->sessionKey,
                ];

                $json = [
                    'gamemode' => self::getGameMode(),
                    'titleid' => Server::getVersion()->titleId,
                    'mapuid' => $map->uid,
                    'mapname' => $map->name,
                    'mapauthor' => $map->author->Login,
                    'isimport' => 'false',
                    'maptime' => CountdownController::getOriginalTimeLimit(),
                    'votes' => $votes,
                ];

                $function = 'saveVotes';
                break;

            default:
                \esc\Classes\Log::error('Invalid MX Record method called.');

                return null;
        }

        //Do the request to mx servers
        $response = self::$client
            ->request($requestMethod, $function, [
                'query' => $query ?? null,
                'json' => $json ?? null,
                'timeout' => 1.5
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
            Log::write(sprintf('%s->%s failed', $requestMethod, $function), isVerbose());
            Log::write($responseBody, isVeryVerbose());

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
            default:
                return "n/a";
        }
    }

    /**
     * Starts MX Karma session
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function startSession()
    {
        Log::info("Starting MX Karma session...");

        $auth = self::call(self::startSession);

        if ($auth) {
            self::$session = $auth;

            $mxResponse = self::call(self::activateSession);

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

    /* +++ 100, ++ 80, + 60, - 40, -- 20, - 0*/
    public static function vote(Player $player, int $rating)
    {
        if (!$player) {
            Log::warning("Null player tries to vote");

            return;
        }

        $map = MapController::getCurrentMap();

        if (!self::playerCanVote($player, $map)) {
            //Prevent players from voting when they didnt finish
            warningMessage('You need to finish the track before you can vote.')->send($player);

            return;
        }

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
                'Map' => $map->id,
                'Rating' => $rating,
            ]);
        }

        self::$updatedVotesPlayerIds->push($player->id);

        infoMessage($player, ' rated this map ', secondary(strtolower(self::$ratings[$rating])))->sendAll();
        Log::info($player . " rated " . $map . " @ $rating|" . self::$ratings[$rating]);
        Template::show($player, 'mx-karma.update-my-vote', compact('rating'));

        self::sendUpdatedKarma();
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
    public static function voteWorst(Player $player)
    {
        if (!$player) {
            Log::warning("Null player tries to vote");

            return;
        }

        $map = MapController::getCurrentMap();

        if (!self::playerCanVote($player, $map)) {
            //Prevent players from voting when they didnt finish
            warningMessage('You need to finish the track before you can vote.')->send($player);

            return;
        }

        $karma = $map->ratings()
            ->wherePlayer($player->id)
            ->get()
            ->first();

        if ($karma != null) {
            $karma->update(['Rating' => 0]);
        } else {
            $karma = Karma::create([
                'Player' => $player->id,
                'Map' => $map->id,
                'Rating' => 0,
            ]);
        }

        self::$updatedVotesPlayerIds->push($player->id);

        infoMessage($player, ' rated this map ', secondary('the worst map ever'))->sendAll();
        Log::info($player . " rated " . $map . " @ 0|worst");
        Template::show($player, 'mx-karma.update-my-vote', compact('rating'));

        self::sendUpdatedKarma();
    }

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        // TODO: Implement start() method.
    }
}
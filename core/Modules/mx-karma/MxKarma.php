<?php

namespace esc\Modules;

use esc\Classes\ChatCommand;
use esc\Classes\DB;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Module;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Controllers\CountdownController;
use esc\Controllers\MapController;
use esc\Interfaces\ModuleInterface;
use esc\Models\Karma;
use esc\Models\Map;
use esc\Models\Player;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Collection;
use stdClass;

class MxKarma extends Module implements ModuleInterface
{
    const startSession = 1;
    const activateSession = 2;
    const getMapRating = 3;
    const saveVotes = 4;

    private static $apiKey;
    private static $mapKarma;
    private static $updatedVotesAverage;

    private static stdClass $session;
    private static string $currentMapUid;

    /**
     * @var array
     */
    private static array $ratings;

    /**
     * @var Collection
     */
    private static $updatedVotesPlayerIds;

    private static bool $offline = false;

    public function __construct()
    {
        if (!config('mx-karma.enabled')) {
            return;
        }

        self::$apiKey = config('mx-karma.key');
        self::$updatedVotesPlayerIds = collect([]);
        self::$ratings = [0 => 'Trash', 20 => 'Bad', 40 => 'Playable', 60 => 'Ok', 80 => 'Good', 100 => 'Fantastic'];

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
        ManiaLinkEvent::add('mx_karma.get_my_rating', [self::class, 'mleGetMyRating']);
    }

    public static function mleGetMyRating(Player $player, string $mapUid)
    {
        $map = DB::table('maps')->select('id')->where('uid', '=', $mapUid)->first();
        $rating = DB::table('mx-karma')->select('Rating')->where('Map', '=', $map->id)->where('Player', '=', $player->id)->first();

        if ($rating) {
            $payload = $rating->Rating;
        } else {
            if (self::playerCanVote($player, $map)) {
                $payload = -1;
            } else {
                $payload = -2;
            }
        }

        Template::show($player, 'mx-karma.update-my-rating', ['rating' => $payload]);
    }

    /**
     * @param Map|null $map
     *
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
        } catch (Exception $e) {
            Log::error('Failed to get MxKarma ratings for ' . $map, isVerbose());
            self::$mapKarma = 50.0;
        }

        self::$currentMapUid = $mapUid;
        self::updateVotesAverage();
        self::sendUpdatedKarma();
    }

    public static function showWidget(Player $player)
    {
        $rating = self::$updatedVotesAverage;
        Template::show($player, 'mx-karma.mx-karma', compact('rating'));
    }

    public static function sendUpdatedKarma()
    {
        $map = MapController::getCurrentMap();
        $mapUid = $map->uid;

        if (self::$currentMapUid != $mapUid) {
            self::$mapKarma = self::call(self::getMapRating);
            self::$currentMapUid = $mapUid;
        }

        self::updateVotesAverage();

        $average = self::$updatedVotesAverage;
        Template::showAll('mx-karma.update-karma', compact('average'));
    }

    public static function playerCanVote(Player $player, $map): bool
    {
        if (DB::table('pbs')->where('player_id', '=', $player->id)->where('map_id', '=', $map->id)->exists()) {
            return true;
        } else if (DB::table('dedi-records')->where('Map', '=', $map->id)->where('Player', '=', $player->id)->exists()) {
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
                Log::error('Invalid MX Record method called.');

                return null;
        }

        //Do the request to mx servers
        $response = RestClient::getClient()
            ->request($requestMethod, "https://karma.mania-exchange.com/api2/$function", [
                'query' => $query ?? null,
                'json' => $json ?? null,
                'timeout' => 5
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
            Karma::create([
                'Player' => $player->id,
                'Map' => $map->id,
                'Rating' => $rating,
            ]);
        }

        self::$updatedVotesPlayerIds->push($player->id);

        infoMessage($player, ' rated this map ', secondary(strtolower(self::$ratings[$rating])))->sendAll();
        Log::info($player . " rated " . $map . " @ $rating|" . self::$ratings[$rating]);

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
            Karma::create([
                'Player' => $player->id,
                'Map' => $map->id,
                'Rating' => 0,
            ]);
        }

        self::$updatedVotesPlayerIds->push($player->id);

        infoMessage($player, ' rated this map ', secondary('the worst map ever'))->sendAll();
        Log::info($player . " rated " . $map . " @ 0|worst");

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
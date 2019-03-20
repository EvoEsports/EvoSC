<?php

namespace esc\Modules;

use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\MXK;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Classes\ChatCommand;
use esc\Controllers\MapController;
use esc\Models\Karma;
use esc\Models\Map;
use esc\Models\Player;
use GuzzleHttp\Client;
use stdClass;

class MxKarma extends MXK
{
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
    private static $updatedVotes;

    public function __construct()
    {
        if (!config('mx-karma.enabled')) {
            return;
        }

        self::$apiKey       = config('mx-karma.key');
        self::$updatedVotes = collect([]);
        self::$ratings      = [0 => 'Trash', 20 => 'Bad', 40 => 'Playable', 60 => 'Ok', 80 => 'Good', 100 => 'Fantastic'];
        self::$client       = new Client([
            'base_uri' => 'https://karma.mania-exchange.com/api2/',
        ]);

        MxKarma::startSession();

        Hook::add('PlayerConnect', [MxKarma::class, 'showWidget']);
        Hook::add('BeginMap', [MxKarma::class, 'beginMap']);
        Hook::add('EndMap', [MxKarma::class, 'endMap']);

        ChatCommand::add('+', [MxKarma::class, 'votePlus'], 'Rate the map ok', null, true);
        ChatCommand::add('++', [MxKarma::class, 'votePlusPlus'], 'Rate the map good', null, true);
        ChatCommand::add('+++', [MxKarma::class, 'votePlusPlusPlus'], 'Rate the map fantastic', null, true);
        ChatCommand::add('-', [MxKarma::class, 'voteMinus'], 'Rate the map playable', null, true);
        ChatCommand::add('--', [MxKarma::class, 'voteMinusMinus'], 'Rate the map bad', null, true);
        ChatCommand::add('---', [MxKarma::class, 'voteMinusMinusMinus'], 'Rate the map trash', null, true);

        \esc\Classes\ManiaLinkEvent::add('mxk.vote', [MxKarma::class, 'vote']);
    }

    /**
     * @param \esc\Models\Map|null $map
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function endMap(Map $map = null)
    {
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

    public static function beginMap(Map $map)
    {
        self::$updatedVotes = collect();

        $mapUid = $map->uid;

        try {
            self::$mapKarma = self::call(MXK::getMapRating);
        } catch (\Exception $e) {
            Log::error('Failed to get MxKarma ratings for ' . $map, isVerbose());
            self::$mapKarma = 50.0;
        }

        self::$currentMap = $mapUid;
        self::updateVotesAverage();
        self::sendUpdatedKarma();

        $playerIds = onlinePlayers()->pluck('id');

        $ratings = $map->ratings()->whereIn('Player', $playerIds)->get()->pluck('Rating', 'Player');

        onlinePlayers()->each(function (Player $player) use ($ratings) {
            if ($ratings->has($player->id)) {
                $rating = $ratings->get($player->id);
            } else {
                $rating = self::playerCanVote($player) ? -1 : -2; // -1 = can vote, -2 = can't vote
            }

            Template::show($player, 'mx-karma.update-my-vote', compact('rating'));
            //TODO: Use multicall
        });
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
            self::$mapKarma   = self::call(MXK::getMapRating);
            self::$currentMap = $mapUid;
        }

        self::updateVotesAverage();

        $average = self::$updatedVotesAverage;
        Template::showAll('mx-karma.update-karma', compact('average'));
    }

    public static function playerCanVote(Player $player): bool
    {
        if ($player->Score > 0) {
            return true;
        }

        $map = MapController::getCurrentMap();

        if (!$map) {
            return false;
        }

        if ($map->ratings()
                ->wherePlayer($player->id)
                ->count() == 1) {
            return true;
        }

        if ($map->locals()
                ->wherePlayer($player->id)
                ->count() == 1) {
            return true;
        }

        if ($map->dedis()
                ->wherePlayer($player->id)
                ->count() == 1) {
            return true;
        }

        return false;
    }

    public static function updateVotesAverage()
    {
        $map   = MapController::getCurrentMap();
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
                    'sessionKey'     => self::$session->sessionKey,
                    'activationHash' => hash("sha512", (self::$apiKey . self::$session->sessionSeed)),
                ];

                $function = 'activateSession';
                break;

            case MXK::getMapRating:
                $requestMethod = 'POST';

                $query = [
                    'sessionKey' => self::$session->sessionKey,
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
                    'sessionKey' => self::$session->sessionKey,
                ];

                $json = [
                    'gamemode'  => self::getGameMode(),
                    'titleid'   => Server::getVersion()->titleId,
                    'mapuid'    => $map->Uid,
                    'mapname'   => $map->gbx->Name,
                    'mapauthor' => $map->gbx->AuthorLogin,
                    'isimport'  => 'false',
                    'maptime'   => MapController::getTimeLimit() * 10,
                    'votes'     => $votes,
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

        $auth = self::call(MXK::startSession);

        if ($auth) {
            self::$session = $auth;

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

    /* +++ 100, ++ 80, + 60, - 40, -- 20, - 0*/
    public static function vote(Player $player, int $rating)
    {
        if (!$player) {
            Log::warning("Null player tries to vote");

            return;
        }

        if (!self::playerCanVote($player)) {
            //Prevent players from voting when they didnt finish
            warningMessage('You need to finish the track before you can vote.')->send($player);

            return;
        }

        $map = MapController::getCurrentMap();

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
}
<?php

namespace EvoSC\Modules\MxKarma;

use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\MapController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Collection;

class MxKarma extends Module implements ModuleInterface
{
    const MANIAPLANET_MXKARMA_URL = 'https://karma.mania-exchange.com/api2';
    const TRACKMANIA_MXKARMA_URL = 'https://karma.trackmania.exchange/api2';

    const ratings = [0 => 'Trash', 20 => 'Bad', 40 => 'Playable', 60 => 'Ok', 80 => 'Good', 100 => 'Fantastic'];

    private static string $apiUrl;
    private static string $sessionKey;
    private static Collection $newVotes;

    private static bool $offline = true;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isManiaPlanet()) {
            self::$apiUrl = self::MANIAPLANET_MXKARMA_URL;
        } else {
            self::$apiUrl = self::TRACKMANIA_MXKARMA_URL;
        }

        self::$newVotes = collect();

        Hook::add('BeginMap', [self::class, 'beginMap']);
        //Hook::add('EndMap', [self::class, 'endMap']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('PlayerPb', [self::class, 'playerPb']);

        ChatCommand::add('+', [self::class, 'votePlus'], 'Rate the map ok', null, true);
        ChatCommand::add('++', [self::class, 'votePlusPlus'], 'Rate the map good', null, true);
        ChatCommand::add('+++', [self::class, 'votePlusPlusPlus'], 'Rate the map fantastic', null, true);
        ChatCommand::add('-', [self::class, 'voteMinus'], 'Rate the map playable', null, true);
        ChatCommand::add('--', [self::class, 'voteMinusMinus'], 'Rate the map bad', null, true);
        ChatCommand::add('---', [self::class, 'voteMinusMinusMinus'], 'Rate the map trash', null, true);
        ChatCommand::add('-----', [self::class, 'voteWorst'], 'Rate it the worst map ever', null, true);

        ManiaLinkEvent::add('mxk.vote', [self::class, 'vote']);

        /*
        $promise = RestClient::getAsync(self::$apiUrl . '/startSession', [
            'query' => [
                'serverLogin' => Server::getSystemInfo()->serverLogin,
                'applicationIdentifier' => 'EvoSC v' . getEvoSCVersion(),
                'testMode' => 'false',
            ]
        ]);

        $promise->then(function (ResponseInterface $response) {
            if ($response->getStatusCode() == 200) {
                $mxResponse = json_decode($response->getBody());

                if (!$mxResponse->success || !isset($mxResponse->success)) {
                    Log::warning("startSession failed: " . $response->getBody());
                    return;
                }

                Log::info("MX Karma session created. Activating...");

                $activationPromise = RestClient::getAsync(self::$apiUrl . '/activateSession', [
                    'query' => [
                        'sessionKey' => $mxResponse->data->sessionKey,
                        'activationHash' => hash("sha512", config('mx-karma.key') . $mxResponse->data->sessionSeed),
                    ]
                ]);

                $activationPromise->then(function (ResponseInterface $activationResponse) use ($mxResponse) {
                    if ($activationResponse->getStatusCode() == 200) {
                        $activationResponse = json_decode($activationResponse->getBody());

                        if (!$activationResponse->data->activated || !isset($activationResponse->data->activated)) {
                            Log::warning('Could not activate MxKarma session.');

                            return;
                        }

                        Log::info("MX Karma session activated.");

                        self::$offline = false;
                        self::$sessionKey = $mxResponse->data->sessionKey;

                        self::registerEvents();
                    } else {
                        Log::warning('Failed to activate MXKarma session: ' . $activationResponse->getReasonPhrase());
                    }
                }, function (RequestException $e) {
                    Log::warning('Failed to activate MXKarma session: ' . $e->getMessage());
                });
            } else {
                Log::warning('Failed to start MXKarma session: ' . $response->getReasonPhrase());
            }
        }, function (RequestException $e) {
            Log::warning('Failed to start MXKarma session: ' . $e->getMessage());
            self::registerEvents();
        });
        */
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        $map = MapController::getCurrentMap();

        $vote = DB::table('mx-karma')->where('Player', '=', $player->id)->where('map', '=', $map->id)->first();

        if (!$vote) {
            $vote = self::playerCanVote($player, $map) ? -1 : -2;
        } else {
            $vote = $vote->Rating;
        }

        if ($vote) {
            Template::show($player, 'MxKarma.update-my-rating', [
                'rating' => $vote,
                'uid' => $map->uid
            ]);
        }

        self::sendVoteData($map, $player);
        Template::show($player, 'MxKarma.mx-karma');
    }

    /**
     * @param Player $player
     * @param int $score
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function playerPb(Player $player, int $score)
    {
        if ($score == 0) {
            return;
        }
        $map = MapController::getCurrentMap();
        $vote = DB::table('mx-karma')->where('Player', '=', $player->id)->where('map', '=', $map->id)->exists();
        if (!$vote) {
            Template::show($player, 'MxKarma.update-my-rating', ['rating' => -1, 'uid' => $map->uid]);
        }
    }

    /**
     * @param Map $map
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function beginMap(Map $map)
    {
        self::sendVoteData($map);

        $votes = DB::table('players')
            ->select(['players.id', 'Login', 'Rating', 'Map', 'pbs.Score'])
            ->leftJoin('mx-karma', function ($join) use ($map) {
                $join->on('mx-karma.Player', '=', 'players.id')
                    ->where('mx-karma.Map', '=', $map->id);
            })
            ->leftJoin('pbs', 'pbs.player_id', '=', 'players.id')
            ->whereIn('players.id', onlinePlayers()->pluck('id'))
            ->groupBy(['players.id', 'Login', 'Rating', 'Map', 'pbs.Score'])
            ->get();

        foreach ($votes as $vote) {
            if (is_null($vote->Rating)) {
                if (is_null($vote->Score)) {
                    $rating = -2;
                } else {
                    $rating = -1;
                }
            } else {
                $rating = $vote->Rating;
            }

            Template::show($vote->Login, 'MxKarma.update-my-rating', ['rating' => $rating, 'uid' => $map->uid], true);
        }

        Template::executeMulticall();
    }

    /**
     * @param Map $map
     */
    public static function endMap(Map $map)
    {
        //Disabled until the new APi is set/fixed
        /*
        if (self::$offline) {
            return;
        }

        $ratings = $map->ratings()->where('new', '=', 1)->get();

        if ($ratings->isEmpty()) {
            return;
        }

        $ratings->transform(function (Karma $rating) {
            return [
                'login' => $rating->player->Login,
                'nickname' => $rating->player->NickName,
                'vote' => $rating->Rating,
            ];
        });

        $promise = RestClient::postAsync(self::$apiUrl . '/saveVotes', [
            'query' => [
                'sessionKey' => self::$sessionKey,
            ],
            'json' => [
                'gamemode' => self::getGameMode(),
                'titleid' => Server::getVersion()->titleId,
                'mapuid' => $map->uid,
                'mapname' => $map->name,
                'mapauthor' => $map->author->Login,
                'isimport' => 'false',
                'maptime' => CountdownController::getOriginalTimeLimitInSeconds(),
                'votes' => $ratings->toArray(),
            ]
        ]);

        $promise->then(function (ResponseInterface $response) {
            if ($response->getStatusCode() != 200) {
                Log::warning('Failed to save karma-votes: ' . $response->getReasonPhrase());
            }
        }, function (RequestException $e) {
            Log::warning('Failed to save map ratings: ' . $e->getMessage());
        });

        DB::table('mx-karma')
            ->where('Map', '=', $map->id)
            ->where('new', '=', 1)
            ->update(['new' => 0]);
        */
    }

    /**
     * @param $mapUid
     * @param array $playerLogins
     * @param int $timeout
     * @return PromiseInterface
     */
    private static function getMapRatingAsync($mapUid, $playerLogins = [], $timeout = 25)
    {
        if (!isset(self::$sessionKey) || self::$offline) {
            return new Promise();
        }

        return RestClient::postAsync(self::$apiUrl . "/getMapRating", [
            'query' => [
                'sessionKey' => self::$sessionKey,
            ],
            'json' => [
                'gamemode' => self::getGameMode(),
                'titleid' => Server::getVersion()->titleId,
                'mapuid' => $mapUid,
                'getvotesonly' => 'true',
                'playerlogins' => $playerLogins,
            ],
            'timeout' => $timeout
        ]);
    }

    /**
     * @param Player $player
     * @param int $rating
     * @param bool $silent
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function vote(Player $player, int $rating, bool $silent = false)
    {
        $map = MapController::getCurrentMap();

        if (!self::playerCanVote($player, $map)) {
            //Prevent players from voting when they didnt finish
            warningMessage('You need to finish the track before you can vote.')->send($player);

            return;
        }

        $karma = DB::table('mx-karma')
            ->where('Map', '=', $map->id)
            ->where('Player', '=', $player->id)
            ->first();

        if ($karma) {
            if ($karma->Rating == $rating) {
                //Prevent spam
                infoMessage('You already rated this map ', secondary(strtolower(self::ratings[$rating])))->send($player);
                return;
            }

            DB::table('mx-karma')
                ->where('Map', '=', $map->id)
                ->where('Player', '=', $player->id)
                ->update([
                    'Rating' => $rating,
                    'new' => 1
                ]);
        } else {
            DB::table('mx-karma')->insert([
                'Player' => $player->id,
                'Map' => $map->id,
                'Rating' => $rating,
                'new' => 1
            ]);
        }

        if (!$silent) {
            infoMessage($player, ' rated this map ', secondary(strtolower(self::ratings[$rating])))->sendAll();
        }

        Template::show($player, 'MxKarma.update-my-rating', [
            'rating' => $rating,
            'uid' => $map->uid
        ]);

        self::$newVotes->put($player->Login, $rating);
        self::sendVoteData($map);

        // $player, $map, $rating, $isNew
        Hook::fire('PlayerRateMap', $player, $map, $rating, $karma == null);
    }

    /**
     * @param Map $map
     * @param Player|null $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendVoteData(Map $map, Player $player = null)
    {
        $average = -1;

        if (DB::table('mx-karma')->where('Map', '=', $map->id)->exists()) {
            $average = DB::table('mx-karma')
                ->selectRaw('AVG(Rating) as rating_avg')
                ->where('Map', '=', $map->id)
                ->first()
                ->rating_avg;
        }

        $data = [
            'average' => $average,
            'uid' => $map->uid
        ];

        if (is_null($player)) {
            Template::showAll('MxKarma.update-karma', $data);
        } else {
            Template::show($player, 'MxKarma.update-karma', $data);
        }
    }

    /**
     * @param Player $player
     * @param $map
     * @return bool
     */
    private static function playerCanVote(Player $player, $map): bool
    {
        if (DB::table('pbs')->where('player_id', '=', $player->id)->where('map_id', '=', $map->id)->exists()) {
            return true;
        } else if (isManiaPlanet()) {
            if (DB::table('dedi-records')->where('Map', '=', $map->id)->where('Player', '=', $player->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns current game mode string
     *
     * @return string
     */
    private static function getGameMode(): string
    {
        switch (Server::getGameMode()) {
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
     * @param Player $player
     */
    public static function votePlusPlusPlus(Player $player)
    {
        self::vote($player, 100);
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
    public static function votePlus(Player $player)
    {
        self::vote($player, 60);
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
        self::vote($player, 0, true);
        infoMessage($player, ' rated this map ', secondary('the worst map ever'))->sendAll();
    }
}

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
use esc\Modules\Classes\MxKarmaMapRating;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class MxKarma extends Module implements ModuleInterface
{
    const ratings = [0 => 'Trash', 20 => 'Bad', 40 => 'Playable', 60 => 'Ok', 80 => 'Good', 100 => 'Fantastic'];

    private static string $sessionKey;

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if ($isBoot) {
            self::startAndActivateSession();
        }

        Hook::add('BeginMap', [self::class, 'beginMap']);
        Hook::add('EndMap', [self::class, 'endMap']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);

        ChatCommand::add('+', [self::class, 'votePlus'], 'Rate the map ok', null, true);
        ChatCommand::add('++', [self::class, 'votePlusPlus'], 'Rate the map good', null, true);
        ChatCommand::add('+++', [self::class, 'votePlusPlusPlus'], 'Rate the map fantastic', null, true);
        ChatCommand::add('-', [self::class, 'voteMinus'], 'Rate the map playable', null, true);
        ChatCommand::add('--', [self::class, 'voteMinusMinus'], 'Rate the map bad', null, true);
        ChatCommand::add('---', [self::class, 'voteMinusMinusMinus'], 'Rate the map trash', null, true);
        ChatCommand::add('-----', [self::class, 'voteWorst'], 'Rate it the worst map ever', null, true);

        ManiaLinkEvent::add('mxk.vote', [self::class, 'vote']);
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        $map = MapController::getCurrentMap();
        $promise = self::getMapRatingAsync($map->uid, [$player->Login]);

        $promise->then(function (ResponseInterface $response) use ($player, $map) {
            if ($response->getStatusCode() == 200) {
                $mxResponse = json_decode($response->getBody());
                $ratings = new MxKarmaMapRating($mxResponse->data);

                $vote = $ratings->getVotes()->first();
                if (!$vote) {
                    $vote = self::playerCanVote($player, $map) ? -1 : -2;
                }

                Template::show($player, 'mx-karma.update-my-rating', [
                    'rating' => $vote,
                    'uid' => $map->uid
                ]);
            }
        }, function (RequestException $e) use ($player) {
            warningMessage('Failed to load MxKarma vote.')->send($player);
            Log::warning('Failed to load MxKarma vote: ' . $e->getMessage());
        });

        Template::show($player, 'mx-karma.mx-karma');
    }

    /**
     * @param Map $map
     */
    public static function beginMap(Map $map)
    {
        $players = onlinePlayers();
        $promise = self::getMapRatingAsync($map->uid, $players->pluck('Login')->toArray());

        $promise->then(function (ResponseInterface $response) use ($players, $map) {
            if ($response->getStatusCode() == 200) {
                $mxResponse = json_decode($response->getBody());

                //Check if method was executed properly
                if (!$mxResponse->success) {
                    Log::warning("getMapRating failed: " . $response->getBody());
                }

                $ratings = new MxKarmaMapRating($mxResponse->data);
                $players = $players->keyBy('Login');
                $massInsert = collect();

                foreach ($ratings->getVotes() as $login => $vote) {
                    $player = $players->get($login);

                    if (DB::table('mx-karma')->where('Map', '=', $map->id)->where('Player', '=', $player->id)->exists()) {
                        DB::table('mx-karma')->where('Map', '=', $map->id)->where('Player', '=', $player->id)->update([
                            'Rating' => $vote
                        ]);
                    } else {
                        $massInsert->push([
                            'Map' => $map->id,
                            'Player' => $player->id,
                            'Rating' => $vote
                        ]);
                    }

                    Template::show($player, 'mx-karma.update-my-rating', [
                        'rating' => $vote,
                        'uid' => $map->uid
                    ], true);
                }

                DB::table('mx-karma')->insert($massInsert->toArray());

                Template::showAll('mx-karma.update-karma', [
                    'average' => $ratings->getVoteAvg(),
                    'total' => $ratings->getTotalVotes(),
                    'uid' => $map->uid
                ]);

                $ratingLogins = $ratings->getVotes()->keys()->flip();

                foreach ($players->diffKeys($ratingLogins) as $player) {
                    Template::show($player, 'mx-karma.update-my-rating', [
                        'rating' => self::playerCanVote($player, $map) ? -1 : -2,
                        'uid' => $map->uid
                    ], true);
                }

                Template::executeMulticall();

                Log::info('Map ratings loaded successfully.');
            } else {
                Log::warning('Connection to MxKarma failed: ' . $response->getReasonPhrase());
            }
        }, function (RequestException $e) {
            Log::warning('Failed to load map ratings: ' . $e->getMessage());
        });
    }

    /**
     * @param Map $map
     */
    public static function endMap(Map $map)
    {
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

        $promise = RestClient::postAsync('https://karma.mania-exchange.com/api2/saveVotes', [
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
                'maptime' => CountdownController::getOriginalTimeLimit(),
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

        DB::table('mx-karma')->where('new', '=', 1)->update(['new' => 0]);
    }

    /**
     * @param $mapUid
     * @param array $playerLogins
     * @param int $timeout
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    private static function getMapRatingAsync($mapUid, $playerLogins = [], $timeout = 25)
    {
        return RestClient::postAsync("https://karma.mania-exchange.com/api2/getMapRating", [
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
     *
     */
    private static function startAndActivateSession()
    {
        $response = RestClient::get('https://karma.mania-exchange.com/api2/startSession', [
            'query' => [
                'serverLogin' => config('server.login'),
                'applicationIdentifier' => 'EvoSC v' . getEscVersion(),
                'testMode' => 'false',
            ]
        ]);

        if ($response->getStatusCode() == 200) {
            $mxResponse = json_decode($response->getBody());

            if (!$mxResponse->success) {
                Log::warning("startSession failed: " . $response->getBody());
            }

            Log::info("MX Karma session created. Activating...");

            $activationResponse = RestClient::get('https://karma.mania-exchange.com/api2/activateSession', [
                'query' => [
                    'sessionKey' => $mxResponse->data->sessionKey,
                    'activationHash' => hash("sha512", config('mx-karma.key') . $mxResponse->data->sessionSeed),
                ]
            ]);

            if ($activationResponse->getStatusCode() == 200) {
                $activationResponse = json_decode($activationResponse->getBody());

                if (!$activationResponse->data->activated || !isset($activationResponse->data->activated)) {
                    Log::warning('Could not activate MxKarma session.');

                    return;
                }

                Log::info("MX Karma session activated.");

                self::$sessionKey = $mxResponse->data->sessionKey;
            } else {
                Log::warning('Connection to MxKarma failed (2): ' . $response->getReasonPhrase());
            }
        } else {
            Log::warning('Connection to MxKarma failed (1): ' . $response->getReasonPhrase());
        }
    }

    /**
     * @param Player $player
     * @param $map
     * @return bool
     */
    private static function playerCanVote(Player $player, $map): bool
    {
        if ($player->Score > 0) {
            return true;
        }

        if (DB::table('pbs')->where('player_id', '=', $player->id)->where('map_id', '=', $map->id)->exists()) {
            return true;
        } else if (DB::table('dedi-records')->where('Map', '=', $map->id)->where('Player', '=', $player->id)->exists()) {
            return true;
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
     * @param int $rating
     * @param bool $silent
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

        Template::show($player, 'mx-karma.update-my-rating', [
            'rating' => $rating,
            'uid' => $map->uid
        ]);
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
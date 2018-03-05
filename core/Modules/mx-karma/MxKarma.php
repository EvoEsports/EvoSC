<?php

include_once __DIR__ . '/MXK.php';

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\controllers\MapController;
use GuzzleHttp\Client;

class MxKarma extends MXK
{
    private static $session;
    private static $apiKey;
    private static $client;
    private static $currentMap;
    private static $mapKarma;

    private static $votes;

    public function __construct()
    {
        MxKarma::setApiKey(config('mxk.key'));

        Template::add('mx-karma', File::get(__DIR__ . '/Templates/mx-karma.latte.xml'));

        $client = new Client([
            'base_uri' => 'https://karma.mania-exchange.com/api2/'
        ]);

        MxKarma::setClient($client);

        MxKarma::startSession();

        Hook::add('PlayerConnect', 'MxKarma::showWidget');
        Hook::add('BeginMap', 'MxKarma::showWidget');
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

    public static function showWidget(...$args)
    {
        $map = Server::getRpc()->getCurrentMapInfo()->uId;

        if (self::$currentMap != $map) {
            self::$mapKarma = self::call(MXK::getMapRating);
            self::$currentMap = $map;
            var_dump(self::$mapKarma);
        }

        if (self::$mapKarma->voteaverage < 30) {
            self::$mapKarma->color = 'f33';
        } elseif (self::$mapKarma->voteaverage < 60) {
            self::$mapKarma->color = 'fc3';
        } else {
            self::$mapKarma->color = config('color.primary');
        }

        Template::showAll('esc.box', [
            'id' => 'MXKarma',
            'title' => 'mx karma',
            'x' => config('ui.mx-karma.x'),
            'y' => config('ui.mx-karma.y'),
            'rows' => 2,
            'scale' => config('ui.mx-karma.scale'),
            'content' => Template::toString('mx-karma', ['karma' => self::$mapKarma])
        ]);
    }

    /**
     * Call MX Karma method
     * @param int $method
     * @return null|stdClass
     */
    public static function call(int $method): ?stdClass
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
                    'titleid' => Server::getRpc()->getVersion()->titleId,
                    'mapuid' => Server::getRpc()->getCurrentMapInfo()->uId,
                    'getvotesonly' => 'false',
                    'playerlogins' => []
                ];

                $function = 'getMapRating';
                break;

            case MXK::saveVotes:
                $requestMethod = 'POST';

                $query = [
                    'sessionKey' => self::getSession()->sessionKey
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
            Log::error('MX Karma method execution failed ' . $requestMethod . '(' . $function . ') [' . implode(', ', $json ?? []) . ']: ' . $mxResponse->data->message);
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
        $gameMode = Server::getRpc()->getGameMode();

        switch ($gameMode) {
            case 0:
                return Server::getRpc()->getScriptName()['CurrentValue'];
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

            if (!$mxResponse->activated) {
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
<?php

include_once __DIR__ . '/MXK.php';

use esc\Classes\File;
use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\controllers\ChatController;
use esc\Models\Map;
use esc\Models\Player;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

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

        self::$votes = new Collection();

        Hook::add('PlayerConnect', 'MxKarma::showWidget');
        Hook::add('BeginMap', 'MxKarma::beginMap');
        Hook::add('EndMap', 'MxKarma::endMap');

        ChatController::addCommand('+', 'MxKarma::votePlus', 'Rate the map good', '');
        ChatController::addCommand('++', 'MxKarma::votePlusPlus', 'Rate the map excellent', '');
        ChatController::addCommand('+++', 'MxKarma::votePlusPlusPlus', 'Rate the map fantastic', '');
        ChatController::addCommand('-', 'MxKarma::voteMinus', 'Rate the map bad', '');
        ChatController::addCommand('--', 'MxKarma::voteMinusMinus', 'Rate the map trash', '');
        ChatController::addCommand('---', 'MxKarma::voteMinusMinusMinus', 'Rate the map unplayable', '');

        \esc\Classes\ManiaLinkEvent::add('mxk.vote', 'MxKarma::vote');
    }

    /* +++ 100, ++ 80, + 60, - 40, -- 20, - 0*/
    public static function vote(Player $player, int $rating)
    {
        Log::info("$player->NickName rated this track @ $rating");

        if (self::$votes->where('player', $player)->isNotEmpty()) {
            self::$votes = self::$votes->whereNotIn('player', $player);
        }

        self::$votes->push([
            'player' => $player,
            'rating' => $rating
        ]);

        $rating = [0 => 'Unplayable', 20 => 'Trash', 40 => 'Ok', 60 => 'Good', 80 => 'Awesome', 100 => 'Fantastic'][$rating];

        ChatController::messageAllNew($player, ' rated this track ', secondary("$rating"));

        self::showWidget();
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
    public static function endMap(Map $map)
    {
        $votes = [];

        foreach (self::$votes as $vote) {
            array_push($votes, [
                'login' => $vote['player']->Login,
                'nickname' => $vote['player']->NickName,
                'vote' => $vote['rating'],
            ]);
        }

        $response = self::call(MXK::saveVotes, $map, $votes);

        if (!$response->updated) {
            Log::warning('Could not update MX Karma.');
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
        self::$votes = new Collection();
        self::showWidget();
    }

    public static function getUpdatedVotesAverage()
    {
        $items = new Collection();

        for ($i = 0; $i < self::$mapKarma->votecount; $i++) {
            $items->push(self::$mapKarma->voteaverage);
        }

        foreach (self::$votes as $vote) {
            $items->push($vote['rating']);
        }

        return $items->average();
    }

    /**
     * Display the widget
     * @param array ...$args
     */
    public static function showWidget(...$args)
    {
        $map = Server::getRpc()->getCurrentMapInfo()->uId;

        if (self::$currentMap != $map) {
            self::$mapKarma = self::call(MXK::getMapRating);
            self::$currentMap = $map;
        }

        $average = self::getUpdatedVotesAverage();

        if ($average < 30) {
            self::$mapKarma->color = 'f33';
        } elseif ($average < 60) {
            self::$mapKarma->color = 'fc3';
        } else {
            self::$mapKarma->color = config('color.primary');
        }

        $barColor = self::hsvToHexRgb($average * 1.5, 100, 100);

        Template::showAll('esc.box', [
            'id' => 'MXKarma',
            'title' => 'mx karma',
            'x' => config('ui.mx-karma.x'),
            'y' => config('ui.mx-karma.y'),
            'rows' => 1.5,
            'scale' => config('ui.mx-karma.scale'),
            'content' => Template::toString('mx-karma', ['karma' => self::$mapKarma, 'average' => $average, 'barColor' => $barColor])
        ]);
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

                $json = [
                    'gamemode' => self::getGameMode(),
                    'titleid' => Server::getRpc()->getVersion()->titleId,
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

    /*
    **  Converts HSV to RGB values
    ** –––––––––––––––––––––––––––––––––––––––––––––––––––––
    **  Reference: http://en.wikipedia.org/wiki/HSL_and_HSV
    **  Purpose:   Useful for generating colours with
    **             same hue-value for web designs.
    **  Input:     Hue        (H) Integer 0-360
    **             Saturation (S) Integer 0-100
    **             Lightness  (V) Integer 0-100
    **  Output:    String "R,G,B"
    **             Suitable for CSS function RGB().
     *
     *  From: https://gist.github.com/vkbo/2323023
    */
    private static function hsvToHexRgb($iH, $iS, $iV)
    {
        if ($iH < 0) $iH = 0;   // Hue:
        if ($iH > 360) $iH = 360; //   0-360
        if ($iS < 0) $iS = 0;   // Saturation:
        if ($iS > 100) $iS = 100; //   0-100
        if ($iV < 0) $iV = 0;   // Lightness:
        if ($iV > 100) $iV = 100; //   0-100
        $dS = $iS / 100.0; // Saturation: 0.0-1.0
        $dV = $iV / 100.0; // Lightness:  0.0-1.0
        $dC = $dV * $dS;   // Chroma:     0.0-1.0
        $dH = $iH / 60.0;  // H-Prime:    0.0-6.0
        $dT = $dH;       // Temp variable
        while ($dT >= 2.0) $dT -= 2.0; // php modulus does not work with float
        $dX = $dC * (1 - abs($dT - 1));     // as used in the Wikipedia link
        switch (floor($dH)) {
            case 0:
                $dR = $dC;
                $dG = $dX;
                $dB = 0.0;
                break;
            case 1:
                $dR = $dX;
                $dG = $dC;
                $dB = 0.0;
                break;
            case 2:
                $dR = 0.0;
                $dG = $dC;
                $dB = $dX;
                break;
            case 3:
                $dR = 0.0;
                $dG = $dX;
                $dB = $dC;
                break;
            case 4:
                $dR = $dX;
                $dG = 0.0;
                $dB = $dC;
                break;
            case 5:
                $dR = $dC;
                $dG = 0.0;
                $dB = $dX;
                break;
            default:
                $dR = 0.0;
                $dG = 0.0;
                $dB = 0.0;
                break;
        }
        $dM = $dV - $dC;
        $dR += $dM;
        $dG += $dM;
        $dB += $dM;
        $dR *= 255;
        $dG *= 255;
        $dB *= 255;

        $dR = str_pad(dechex(round($dR)), 2, "0", STR_PAD_LEFT);
        $dG = str_pad(dechex(round($dG)), 2, "0", STR_PAD_LEFT);
        $dB = str_pad(dechex(round($dB)), 2, "0", STR_PAD_LEFT);

        return $dR . $dG . $dB;
    }
}
<?php

use esc\Classes\Config;
use esc\Classes\Log;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Models\Map;
use esc\Models\Player;

class DedimaniaApi
{
    static $newTimes;
    static $checkpoints;

    /**
     * Connect to dedimania and authenticate
     */
    static function authenticateAndValidateAccount(): ?string
    {
        $response = Dedimania::callStruct('dedimania.OpenSession', [
            'Game' => 'TM2',
            'Login' => Config::get('dedimania.login'),
            'Code' => Config::get('dedimania.key'),
            'Tool' => 'EvoSC',
            'Version' => getEscVersion(),
            'Packmask' => 'Stadium',
            'ServerVersion' => Server::getVersion()->version,
            'ServerBuild' => Server::getVersion()->build,
            'Path' => Server::getDetailedPlayerInfo(Config::get('dedimania.login'))->path
        ]);

        try {
            return (string)$response->params->param->value->array->data->value->array->data->value->struct->member[0]->value->string;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Call a method on dedimania server
     * See documentation at http://dedimania.net:8082/Dedimania
     * @param string $method
     * @param array|null $parameters
     * @return null|SimpleXMLElement
     */
    static function call(string $method, array $parameters = null): ?SimpleXMLElement
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

        $response = RestClient::post('http://dedimania.net:8082/Dedimania', [
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
     * Get dedimania session
     * @return DedimaniaSession|null
     */
    static function getSession(): ?DedimaniaSession
    {
        $session = DedimaniaSession::whereExpired(false)->orderByDesc('updated_at')->first();

        if ($session) {
            Log::info("Dedimania using stored session: $session->Session from $session->created_at");

            $lastCheck = $session->updated_at->diffInMinutes(\Carbon\Carbon::now());

            //Check if session is valid every 5 minutes
            if ($lastCheck > 5) {
                $response = self::call('dedimania.CheckSession', [$session->Session]);
                if ($response && isset($response->params->param->value->boolean)) {
                    $ok = (bool)$response->params->param->value->boolean;
                    if (!$ok) {
                        Log::warning('Dedimania session expired. Creating new.');

                        $session->update(['Expired' => true]);

                        $sessionId = self::authenticateAndValidateAccount();

                        if ($sessionId == null) {
                            Log::warning("Connection to Dedimania failed.");
                            return null;
                        }

                        Log::logAddLine('Dedimania', "Session created: $sessionId");

                        return DedimaniaSession::create(['Session' => $sessionId]);
                    }

                    $session->touch();
                }
            }
        } else {
            $sessionId = self::authenticateAndValidateAccount();

            if ($sessionId == null) {
                Log::logAddLine('Dedimania', "Connection to Dedimania failed.");
                return null;
            }

            Log::logAddLine('Dedimania', "Session created: $sessionId");

            return DedimaniaSession::create(['Session' => $sessionId]);
        }

        return $session;
    }

    /**
     * Call a method on dedimania server
     * See documentation at http://dedimania.net:8082/Dedimania
     * @param string $method
     * @param array|null $parameters
     * @return null|SimpleXMLElement
     */
    static function callStruct(string $method, array $parameters = null): ?SimpleXMLElement
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

        $response = RestClient::post('http://dedimania.net:8082/Dedimania', [
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

    static function setChallengeTimes(Map $map)
    {
        if (count(self::$newTimes) == 0) {
            Log::logAddLine('Dedimania', 'No new times to push');
            return;
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.SetChallengeTimes');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', self::getSession()->Session);

        //MapInfo: struct {'UId': string, 'Name': string, 'Environment': string, 'Author': string, 'NbCheckpoints': int, 'NbLaps': int} from GetCurrentChallengeInfo
        self::paramAddStruct($params->addChild('param'), [
            'UId' => $map->UId,
            'Name' => $map->Name,
            'Environment' => Server::getCurrentMapInfo()->environnement,
            'Author' => $map->Author,
            'NbCheckpoints' => $map->NbCheckpoints,
            'NbLaps' => $map->NbLaps,
        ]);

        //string GameMode
        $params->addChild('param')->addChild('value', 'TA'); //TODO: make dynamic

        //Times: array of struct {'Login': string, 'Best': int, 'Checks': string (list of int, comma separated)}:
        $times = $params->addChild('param')->addChild('array')->addChild('data')->addChild('value');
        foreach (self::$newTimes->sortBy('Score') as $dedi) {
            $struct = $times->addChild('struct');

            $member = $struct->addChild('member');
            $member->addChild('name', 'Login');
            $member->addChild('value', $dedi->player->Login);

            $member = $struct->addChild('member');
            $member->addChild('name', 'Best');
            $member->addChild('value')->addChild('i4', $dedi->Score);

            $member = $struct->addChild('member');
            $member->addChild('name', 'Checks');
//            $array = $member->addChild('value')->addChild('array')->addChild('data');
//
//            $checkTimes = self::$checkpoints->where('player.Login', $dedi->player->Login)->pluck('time')->sortBy('time');
//            foreach ($checkTimes as $time) {
//                $array->addChild('value')->addChild('i4', $time);
//            }
            $member->addChild('value', self::$checkpoints->where('player.Login', $dedi->player->Login)->pluck('time')->sortBy('time')->implode(','));
        }

        //Replays: struct {'VReplay': base64 string, 'VReplayChecks': string (list of int, comma separated), 'Top1GReplay': base64 string}:
        /*
            .. VReplay: validation replay of the best time (ie first) sent, base64 encoded,
            .. VReplayChecks: in special case of Laps mode (for which only best laps checkpoints are in Times), give here all race checkpoints (list of int, comma separated)
            .. Top1GReplay: GhostReplay for the same time as VReplay, base64 encoded: send only if supposed new top1, else send an empty string !
        */
        $bestPlayer = self::$newTimes->sortBy('Score')->first();

        if (!$bestPlayer) {
            Log::logAddLine('Dedimania', 'No best player');
            return;
        }

        $vreplay = Server::getValidationReplay($bestPlayer->player->Login);
        $vreplayChecks = self::$checkpoints->where('player.Login', $bestPlayer->player->Login)->pluck('time')->sortBy('time')->implode(',');
        $top1greplay = '';

        try {
            //Check if there is top1 dedi
            if (self::$newTimes->where('Rank', 1)->isNotEmpty()) {
                $top1greplay = file_get_contents(ghost($dedi->ghostReplayFile));
            }

            self::paramAddStruct($params->addChild('param'), [
                'VReplay' => $vreplay,
                'VReplayChecks' => $vreplayChecks,
                'Top1GReplay' => $top1greplay
            ]);
        } catch (\Maniaplanet\DedicatedServer\Xmlrpc\FaultException $e) {
            Log::error('Error saving dedis: ' . $e->getMessage());
        }

        $xml->asXML(cacheDir(sprintf('dedi_req_%s.xml', time())));

        $data = self::post($xml);
        if ($data) {
            if (isset($data->params->param->value->boolean)) {
                if (!$data->params->param->value->boolean) {
                    \esc\Controllers\ChatController::messageAll('Updating dedis failed');
                }
            }
        }
    }

    static function getChallengeRecords(Map $map)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.GetChallengeRecords');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', self::getSession()->Session);

        //MapInfo: struct {'UId': string, 'Name': string, 'Environment': string, 'Author': string, 'NbCheckpoints': int, 'NbLaps': int} from GetCurrentChallengeInfo
        self::paramAddStruct($params->addChild('param'), [
            'UId' => $map->UId,
            'Name' => $map->Name,
            'Environment' => Server::getCurrentMapInfo()->environnement,
            'Author' => $map->Author,
            'NbCheckpoints' => $map->NbCheckpoints,
            'NbLaps' => $map->NbLaps,
        ]);

        //string GameMode
        $params->addChild('param')->addChild('value', 'TA'); //TODO: make dynamic

        //struct SrvInfo
        self::paramAddStruct($params->addChild('param'), [
            'SrvName' => Server::getServerName(),
            'Comment' => Server::getServerComment(),
            'Private' => Server::getServerPassword() ? true : false,
            'NumPlayers' => onlinePlayers()->count(),
            'MaxPlayers' => 16, //TODO: change form hardcode
            'NumSpecs' => 0,
            'MaxSpecs' => 16 //TODO: change form hardcode
        ]);

        //array Players
        $players = onlinePlayers()->map(function (Player $player) {
            return [
                'Login' => $player->Login,
                'IsSpec' => $player->isSpectator()
            ];
        });

        self::paramAddArray($params->addChild('param'), $players->toArray());

        $responseData = self::post($xml);

        if ($responseData) {
            return $responseData;
        }
    }

    private static function paramAddStruct(SimpleXMLElement $param, array $data)
    {
        $struct = $param->addChild('struct');

        foreach ($data as $key => $value) {
            $member = $struct->addChild('member');
            $member->addChild('name', $key);

            if ($key == 'VReplay') {
                $member->addChild('value')->addChild('base64', base64_encode($value));
                continue;
            }

            if ($key == 'Top1GReplay') {
                $member->addChild('value')->addChild('base64', base64_encode($value));
                continue;
            }

            switch (gettype($value)) {
                case 'integer':
                    $member->addChild('value')->addChild('i4', $value);
                    break;

                case 'boolean':
                    $member->addChild('value')->addChild('boolean', $value);
                    break;

                case 'double':
                    $member->addChild('value')->addChild('double', $value);
                    break;

                case 'array':
                    self::paramAddArray($member->addChild('value'), $value);
                    break;

                default:
                case 'string':
                    $member->addChild('value', $value);
                    break;

            }
        }
    }

    private static function paramAddArray(SimpleXMLElement $param, array $data)
    {
        $array = $param->addChild('array');
        $xmlData = $array->addChild('data');

        foreach ($data as $key => $value) {

            switch (gettype($value)) {
                case 'integer':
                    $xmlData->addChild('value')->addChild('i4', $value);
                    break;

                case 'boolean':
                    $xmlData->addChild('value')->addChild('boolean', $value);
                    break;

                case 'double':
                    $xmlData->addChild('value')->addChild('double', $value);
                    break;

                case 'array':
                    self::paramAddArray($xmlData->addChild('value'), $value);
                    break;

                default:
                case 'string':
                    $xmlData->addChild('value', $value);
                    break;

            }
        }
    }

    private static function post(SimpleXMLElement $xml): ?SimpleXMLElement
    {
        try {
            $response = RestClient::post('http://dedimania.net:8082/Dedimania', [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF8'
                ],
                'body' => $xml->asXML()
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error('Dedimania post failed: ' . $e->getMessage());
            return null;
        }

        if ($response->getStatusCode() != 200) {
            Log::warning("Connection to dedimania failed: " . $response->getReasonPhrase());
            return null;
        }

        $data = $response->getBody()->getContents();

        try {
            return new SimpleXMLElement($data);
        } catch (\Exception $e) {
            return null;
        }
    }
}
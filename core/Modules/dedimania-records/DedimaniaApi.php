<?php

use esc\Classes\Config;
use esc\Classes\Log;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Models\Map;
use esc\Models\Player;

class DedimaniaApi
{
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
            'ServerVersion' => Server::getRpc()->getVersion()->version,
            'ServerBuild' => Server::getRpc()->getVersion()->build,
            'Path' => Server::getRpc()->getDetailedPlayerInfo(Config::get('dedimania.login'))->path
        ]);

        try {
            return (string)$response->params->param->value->array->data->value->array->data->value->struct->member[0]->value->string;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Call a method on dedimania server
     * See documentation at http://dedimania.net:8081/Dedimania
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

        $response = RestClient::post('http://dedimania.net:8081/Dedimania', [
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
                            Log::warning("Connection to Dedimania failed. Using cached values.");
                            return null;
                        }

                        Log::info("Dedimania session created: $sessionId");

                        return DedimaniaSession::create(['Session' => $sessionId]);
                    }

                    $session->touch();
                }
            }
        } else {
            $sessionId = self::authenticateAndValidateAccount();

            if ($sessionId == null) {
                Log::warning("Connection to Dedimania failed. Using cached values.");
                return null;
            }

            Log::info("Dedimania session created: $sessionId");

            return DedimaniaSession::create(['Session' => $sessionId]);
        }

        return $session;
    }

    /**
     * Call a method on dedimania server
     * See documentation at http://dedimania.net:8081/Dedimania
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

        $response = RestClient::post('http://dedimania.net:8081/Dedimania', [
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

    static function getChallengeRecords(Map $map)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.GetChallengeRecords');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value')->addChild('string', self::getSession()->Session);

        //MapInfo: struct {'UId': string, 'Name': string, 'Environment': string, 'Author': string, 'NbCheckpoints': int, 'NbLaps': int} from GetCurrentChallengeInfo
        self::paramAddStruct($params->addChild('param'), [
            'UId' => $map->UId,
            'Name' => $map->Name,
            'Environment' => Server::getRpc()->getCurrentMapInfo()->environnement,
            'Author' => $map->Author,
            'NbCheckpoints' => $map->NbCheckpoints,
            'NbLaps' => $map->NbLaps,
        ]);

        //string GameMode
        $params->addChild('param')->addChild('value', 'TA'); //TODO: make dynamic

        //struct SrvInfo
        self::paramAddStruct($params->addChild('param'), [
            'SrvName' => Server::getRpc()->getServerName(),
            'Comment' => Server::getRpc()->getServerComment(),
            'Private' => Server::getRpc()->getServerPassword() ? true : false,
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
            $responseData->saveXML(cacheDir('dedi.xml'));

            return $responseData;
        }
    }

    private static function paramAddStruct(SimpleXMLElement $param, array $data)
    {
        $struct = $param->addChild('struct');

        foreach ($data as $key => $value) {
            $member = $struct->addChild('member');
            $member->addChild('name', $key);

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
        $response = RestClient::post('http://dedimania.net:8081/Dedimania', [
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF8'
            ],
            'body' => $xml->asXML()
        ]);

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
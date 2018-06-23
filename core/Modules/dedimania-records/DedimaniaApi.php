<?php

namespace esc\Modules\Dedimania;

use esc\Classes\Config;
use esc\Classes\Log;
use esc\Classes\RestClient;
use esc\Classes\Server;
use esc\Models\DedimaniaSession;
use esc\Models\Map;
use esc\Models\Player;
use SimpleXMLElement;

class DedimaniaApi
{
    static $newTimes;

    /**
     * Get dedimania session
     *
     * @return DedimaniaSession|null
     */
    static function getSession(): ?DedimaniaSession
    {
        $session = DedimaniaSession::whereExpired(false)->orderByDesc('updated_at')->first();

        //TODO: Check session expired

        if (!$session) {
            $sessionId = self::openSession();

            if ($sessionId == null) {
                Log::logAddLine('Dedimania', "Connection to Dedimania failed.");

                return null;
            }

            Log::logAddLine(self::class, "Created session: $sessionId", false);

            return DedimaniaSession::create(['Session' => $sessionId]);
        }

        return $session;
    }

    /**
     * Open Dedimania session
     *
     * @return null|string
     */
    static function openSession(): ?string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.OpenSession');
        $params = $xml->addChild('params');

        //struct {'Game': string, 'Login': string, 'Code': string, 'Path': string, 'Packmask': string, 'ServerVersion': string, 'ServerBuild': string, 'Tool': string, 'Version': string, [Optionals: 'ServerIP': string, 'ServerPort': int, 'XmlrpcPort': int]}
        self::paramAddStruct($params->addChild('param'), [
            'Game'          => 'TM2',
            'Login'         => config('dedimania.login'),
            'Code'          => config('dedimania.key'),
            'Path'          => Server::getDetailedPlayerInfo(config('dedimania.login'))->path,
            'Packmask'      => 'Stadium',
            'ServerVersion' => Server::getVersion()->version,
            'ServerBuild'   => Server::getVersion()->build,
            'Tool'          => 'EvoSC',
            'Version'       => getEscVersion(),
            /*
            'ServerIP'      => ,
            'ServerPort'    => ,
            'XmlrpcPort'    => ,
            */
        ]);

        //Send the request
        $data = self::post($xml);

        try {
            return (string)$data->params->param->value->struct->member[0]->value->string;
        } catch (\Exception $e) {
            Log::logAddLine(self::class, 'Failed to create session');
            return null;
        }
    }

    /**
     * Check current session
     *
     * @return null|SimpleXMLElement
     */
    static function checkSession()
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.CheckSession');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', self::getSession()->Session);

        //Send the request
        $data = self::post($xml);

        return $data;
    }

    /**
     * Get records for map
     *
     * @param Map $map
     * @return null|SimpleXMLElement
     */
    static function getChallengeRecords(Map $map)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.GetChallengeRecords');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', self::getSession()->Session);

        //MapInfo: struct {'uid': string, 'Name': string, 'Environment': string, 'Author': string, 'NbCheckpoints': int, 'NbLaps': int} from GetCurrentChallengeInfo
        self::paramAddStruct($params->addChild('param'), [
            'UId'           => $map->gbx->MapUid,
            'Name'          => $map->gbx->Name,
            'Environment'   => $map->gbx->Environment,
            'Author'        => $map->gbx->AuthorLogin,
            'NbCheckpoints' => $map->gbx->CheckpointsPerLaps,
            'NbLaps'        => $map->gbx->NbLaps,
        ]);

        //string GameMode
        $params->addChild('param')->addChild('value', 'TA'); //TODO: make dynamic

        //struct SrvInfo
        self::paramAddStruct($params->addChild('param'), [
            'SrvName'    => Server::getServerName(),
            'Comment'    => Server::getServerComment(),
            'Private'    => Server::getServerPassword() ? true : false,
            'NumPlayers' => onlinePlayers()->count(),
            'MaxPlayers' => 16, //TODO: change form hardcode
            'NumSpecs'   => 0,
            'MaxSpecs'   => 16 //TODO: change form hardcode
        ]);

        //array Players
        $players = onlinePlayers()->map(function (Player $player) {
            return [
                'Login'  => $player->Login,
                'IsSpec' => $player->isSpectator(),
            ];
        });

        self::paramAddArray($params->addChild('param'), $players->toArray());

        $responseData = self::post($xml);

        if ($responseData) {
            return $responseData;
        }
    }

    /**
     * Update connected players
     *
     * @param Map $map
     * @return null|SimpleXMLElement
     */
    static function updateServerPlayers(Map $map)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.UpdateServerPlayers');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', self::getSession()->Session);

        //struct SrvInfo
        self::paramAddStruct($params->addChild('param'), [
            'SrvName'    => Server::getServerName(),
            'Comment'    => Server::getServerComment(),
            'Private'    => Server::getServerPassword() ? true : false,
            'NumPlayers' => onlinePlayers()->count(),
            'MaxPlayers' => 16, //TODO: change form hardcode
            'NumSpecs'   => 0,
            'MaxSpecs'   => 16 //TODO: change form hardcode
        ]);

        //struct votesInfo
        self::paramAddStruct($params->addChild('param'), [
            'UId'      => $map->uid,
            'GameMode' => 'TA' //Change from hardcode
        ]);

        //array Players (array of struct: {'Login': string, 'IsSpec': boolean, 'Vote': int (-1 = unchanged)})
        $players = onlinePlayers()->map(function (Player $player) {
            return [
                'Login'  => $player->Login,
                'IsSpec' => $player->isSpectator(),
                'Vote'   => -1,
            ];
        });

        self::paramAddArray($params->addChild('param'), $players->toArray());

        $responseData = self::post($xml);

        if ($responseData) {
            return $responseData;
        }
    }

    /**
     * Send new records
     *
     * @param Map $map
     */
    static function setChallengeTimes(Map $map)
    {
        if (count(self::$newTimes) == 0) {
            //No new records

            Log::logAddLine('Dedimania', 'No records made', false);
            return;
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.SetChallengeTimes');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', self::getSession()->Session);

        //MapInfo: struct {'uid': string, 'Name': string, 'Environment': string, 'Author': string, 'NbCheckpoints': int, 'NbLaps': int} from GetCurrentChallengeInfo
        self::paramAddStruct($params->addChild('param'), [
            'uid'           => $map->gbx->MapUid,
            'Name'          => $map->gbx->Name,
            'Environment'   => $map->gbx->Environment,
            'Author'        => $map->gbx->AuthorLogin,
            'NbCheckpoints' => $map->gbx->CheckpointsPerLaps,
            'NbLaps'        => $map->gbx->NbLaps,
        ]);

        //string GameMode
        $params->addChild('param')->addChild('value', 'TA'); //TODO: make dynamic

        //Times: array of struct {'Login': string, 'Best': int, 'Checks': string (list of int, comma separated)}:
        $times = $params->addChild('param')->addChild('array')->addChild('data')->addChild('value');
        foreach (self::$newTimes->sortBy('Score') as $dedi) {
            if ($dedi->Rank > 100) {
                continue;
            }

            $checkpoints = $dedi->Checkpoints;

            $struct = $times->addChild('struct');

            $member = $struct->addChild('member');
            $member->addChild('name', 'Login');
            $member->addChild('value', $dedi->player->Login);

            $member = $struct->addChild('member');
            $member->addChild('name', 'Best');
            $member->addChild('value')->addChild('i4', $dedi->Score);

            $member = $struct->addChild('member');
            $member->addChild('name', 'Checks');
            $member->addChild('value', $checkpoints);
        }

        //Replays: struct {'VReplay': base64 string, 'VReplayChecks': string (list of int, comma separated), 'Top1GReplay': base64 string}:
        /*
            .. VReplay: validation replay of the best time (ie first) sent, base64 encoded,
            .. VReplayChecks: in special case of Laps mode (for which only best laps checkpoints are in Times), give here all race checkpoints (list of int, comma separated)
            .. Top1GReplay: GhostReplay for the same time as VReplay, base64 encoded: send only if supposed new top1, else send an empty string !
        */

        $bestRecord = self::$newTimes->sortBy('Score')->first();

        try {
            $VReplay = Server::getValidationReplay($bestRecord->player->Login);
        } catch (\Exception $e) {
            $VReplay = $e->getMessage();
            Log::logAddLine('DedimaniaApi', 'Failed to get validation replay for player ' . $bestRecord->player->Login . ': ' . $e->getMessage());
            Log::logAddLine('DedimaniaApi', $e->getTraceAsString(), false);
        }

        $VReplayChecks = $map->dedis()->wherePlayer($bestRecord->id)->first()->Checkpoints ?? "";
        $Top1GReplay   = '';

        try {
            //Check if there is top1 dedi
            if (self::$newTimes->where('Rank', 1)->isNotEmpty()) {
                $Top1GReplay = file_get_contents(ghost($dedi->ghostReplayFile)) ?? "";
            }

            //Add replays
            self::paramAddStruct($params->addChild('param'), [
                'VReplay'       => $VReplay,
                'VReplayChecks' => $VReplayChecks,
                'Top1GReplay'   => $Top1GReplay,
            ]);

            //Send the request
            $data = self::post($xml);

            if ($data) {
                //Got response

                if (isset($data->params->param->value->boolean)) {
                    //Success parameter is set

                    if (!$data->params->param->value->boolean) {
                        //Request failed

                        \esc\Controllers\ChatController::messageAll('Updating dedis failed');
                    }
                }
            }
        } catch (\Maniaplanet\DedicatedServer\Xmlrpc\FaultException $e) {
            Log::error('Error saving dedis: ' . $e->getMessage());
        }
    }

    /*dedimania.PlayerConnect(string SessionId, string Login, string Nickname, string Path, boolean IsSpec).

Return struct {'Login': string, 'MaxRank': int, 'Banned': boolean, 'OptionsEnabled': boolean, 'ToolOption': string}, where:
. MaxRank: max rank for player records,
. Banned: ban status on Dedimania for the player,
. OptionsEnabled: true if tool options can be stored for the player,
. ToolOption: optional value stored for the player by the used tool (can usually be config/layout values, and storable only if player has OptionsEnabled).
    */
    public static function playerConnect(Player $player)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.PlayerConnect');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('string', self::getSession()->Session);

        //string Login
        $params->addChild('param')->addChild('string', $player->Login);

        //string Nickname
        $params->addChild('param')->addChild('string', $player->NickName);

        //string Path
        $params->addChild('param')->addChild('string', Server::getDetailedPlayerInfo(Config::get('dedimania.login'))->path);

        //boolean IsSpec
        $params->addChild('param')->addChild('boolean', $player->isSpectator());

        $responseData = self::post($xml);

        if (isset($responseData->params->param->value->struct->member) && $responseData->params->param->value->struct->member[0]->value->string == $player->Login) {
            $playerData = $responseData->params->param->value->struct;

            $player->update([
                'MaxRank' => (int)$playerData->member[1]->value->string,
                'Banned'  => ($playerData->member[2]->value->int != "0")
            ]);

            if ($player->Banned) {
                \esc\Controllers\ChatController::messageAll($player, warning(' is banned from dedimania.'));
            }
        }

        if ($responseData) {
            return $responseData;
        }
    }

    /**
     * Convert array to struct and attach it to given param
     *
     * @param SimpleXMLElement $param
     * @param array $data
     */
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

    /**
     * Adds array to given param
     *
     * @param SimpleXMLElement $param
     * @param array $data
     */
    private static function paramAddArray(SimpleXMLElement $param, array $data)
    {
        $array   = $param->addChild('array');
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

    /**
     * Send a request to dedimania
     *
     * @param SimpleXMLElement $xml
     * @return null|SimpleXMLElement
     */
    private static function post(SimpleXMLElement $xml): ?SimpleXMLElement
    {
        try {
            $response = RestClient::post('http://dedimania.net:8082/Dedimania', [
                'headers'        => [
                    'Content-Type'    => 'text/xml; charset=UTF8',
                    'Accept-Encoding' => 'gzip',
                ],
                'decode_content' => 'gzip',
                'body'           => $xml->asXML(),
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
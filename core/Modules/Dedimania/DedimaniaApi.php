<?php

namespace EvoSC\Modules\Dedimania;


use Carbon\Carbon;
use EvoSC\Classes\Cache;
use EvoSC\Classes\DB;
use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Server;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

class DedimaniaApi extends Module
{
    protected static bool $enabled = false;
    protected static int $maxRank = 30;

    /**
     * dedimania.OpenSession
     * struct dedimania.OpenSession(struct)
     * Server authentication and open session.
     *
     * dedimania.OpenSession(struct {'Game': string, 'Login': string, 'Code': string, 'Path': string, 'Packmask': string, 'ServerVersion': string, 'ServerBuild': string, 'Tool': string, 'Version': string, [Optionals: 'ServerIP': string, 'ServerPort': int, 'XmlrpcPort': int]}):
     * . Game: can be 'TM2' only actually,
     * . Login: server login,
     * . Code: indicated when registering the server login on Dedimania site,
     * . Path: path of server login (from GetDetailedPlayerInfo(server_login)),
     * . Packmask: value returned by GetServerPackMask (ie 'Canyon','Stadium',Valley','Lagoon'),
     * . ServerVersion, ServerBuild: server Version and Build (from GetVersion()),
     * . Tool, Version: the script/tool name and version.
     *
     * Return struct {'SessionId': string, 'Error': string}
     * . If successful SessionId is the value to be used it other methods, if not it is empty and a message is in Error.
     */
    protected static function openSession(): bool
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.OpenSession');
        $params = $xml->addChild('params');

        foreach (['login', 'key'] as $necessaryConfig) {
            if (config('dedimania.' . $necessaryConfig) == "") {
                Log::write(sprintf('Necessary config value %s is not set, dedimania stays disabled.',
                    $necessaryConfig));

                return false;
            }
        }

        self::paramAddStruct($params->addChild('param'), [
            'Game' => 'TM2',
            'Login' => config('dedimania.login'),
            'Code' => config('dedimania.key'),
            'Path' => Server::getDetailedPlayerInfo(config('dedimania.login'))->path,
            'Packmask' => config('server.title', 'Stadium'),
            'ServerVersion' => Server::getVersion()->version,
            'ServerBuild' => Server::getVersion()->build,
            'Tool' => 'EvoSC',
            'Version' => getEvoSCVersion(),
            /* Optional
            'ServerIP'      => ,
            'ServerPort'    => ,
            'XmlrpcPort'    => ,
            */
        ]);

        //Send the request
        $data = self::post($xml);

        if (isset($data->params->param->value->struct->member->name) && $data->params->param->value->struct->member->name == 'SessionId') {
            $sessionKey = $data->params->param->value->struct->member->value->string;
            if (self::setSessionKey($sessionKey)) {
                Log::write('Session created and saved.');
            }

            return true;
        }

        Log::write('Failed to open session at dedimania.');

        return false;
    }

    /**
     * dedimania.CheckSession
     * boolean dedimania.CheckSession(string)
     * dedimania.CheckSession(string SessionId).
     * Need authenticated session.
     *
     * Return true if session is ok, else false and you get an authenticated error in dedimania.WarningsAndTTR result.
     */
    protected static function checkSession(): bool
    {
        $sessionKey = self::getSessionKey();

        if (!$sessionKey) {
            return false;
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.CheckSession');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', $sessionKey);

        //Send the request
        $data = self::post($xml);

        if (isset($data->params->param->value->boolean)) {
            return $data->params->param->value->boolean == "1";
        }

        Log::write('Failed to check session.');

        return false;
    }

    /**
     * dedimania.GetChallengeRecords
     * struct dedimania.GetChallengeRecords(string, struct, string, struct, array)
     * Get/Set current challenge and server info, get map records, get players infos. Called at BeginRace.
     * Need authenticated session.
     *
     * dedimania.GetChallengeRecords(string SessionId, struct MapInfo, string GameMode, struct SrvInfo, array Players):
     * . MapInfo: struct {'UId': string, 'Name': string, 'Environment': string, 'Author': string, 'NbCheckpoints': int, 'NbLaps': int} from GetCurrentChallengeInfo,
     * . GameMode: 'Rounds' or 'TA' (or eventually a future special mode),
     * . SrvInfo: struct {'SrvName': string, 'Comment': string, 'Private': boolean, 'NumPlayers': int, 'MaxPlayers': int, 'NumSpecs': int, 'MaxSpecs': int},
     * . Players: array of struct {'Login': string, 'IsSpec': boolean},
     *
     * Return struct {'UId': string, 'ServerMaxRank': int, 'AllowedGameModes': string (list of string, comma separated), 'Records': array of struct {'Login': string, 'NickName': string, 'Best': int, 'Rank': int, 'MaxRank': int, 'Checks': string (list of int, comma separated), 'Vote': int}, 'Players': array of {'Login': string, 'MaxRank': int}, 'TotalRaces': int, 'TotalPlayers': int }:
     * . ServerMaxRank: the nominal max number of records for this server,
     * . MaxRank in records: the max record rank for the record (can be bigger than ServerMaxRank),
     * . MaxRank in players: the max record rank for the player (can be bigger than ServerMaxRank!),
     * . Checks: checkpoints times of the associated record.
     * . Vote: 0 to 100 value (or -1 if player did not vote for the map).
     *
     * @param Map $map
     * @param bool $isTimeAttack
     * @return null
     */
    static function getChallengeRecords(Map $map, bool $isTimeAttack)
    {
        $mpMap = Server::getCurrentMapInfo();
        Log::write(sprintf('getChallengeRecords(%s)', $mpMap->uId));

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.GetChallengeRecords');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', self::getSessionKey());

        self::paramAddStruct($params->addChild('param'), [
            'UId' => $mpMap->uId,
            'Name' => str_replace('&', '', $mpMap->name),
            'Environment' => $mpMap->environnement,
            'Author' => $mpMap->author,
            'NbCheckpoints' => $mpMap->nbCheckpoints,
            'NbLaps' => $mpMap->nbLaps,
        ]);

        //string GameMode
        $params->addChild('param')->addChild('value', $isTimeAttack ? 'TA' : 'Rounds');

        //struct SrvInfo
        self::paramAddStruct($params->addChild('param'), [
            'SrvName' => Server::getServerName(),
            'Comment' => Server::getServerComment(),
            'Private' => Server::getServerPassword() ? 1 : 0,
            'NumPlayers' => onlinePlayers()->where('spectator_status', 0)->count(),
            'MaxPlayers' => Server::getMaxPlayers()['CurrentValue'],
            'NumSpecs' => onlinePlayers()->where('spectator_status', '>', 0)->count(),
            'MaxSpecs' => Server::getMaxSpectators()['CurrentValue'],
        ]);

        //array Players
        $players = onlinePlayers()->map(function (Player $player) {
            return [
                'Login' => $player->Login,
                'IsSpec' => $player->isSpectator() ? 1 : 0,
            ];
        });

        self::paramAddArray($params->addChild('param'), $players->toArray());

        self::postAsync($xml, function (SimpleXMLElement $responseData) use ($map) {
            if ($responseData == null) {
                return null;
            }

            if (isset($responseData->fault)) {
                //Error
                $errorMsg = $responseData->fault->value->struct->member[1]->value->string;
                Log::error($errorMsg);

                return null;
            }

            $maxRank = intval($responseData->params->param->value->struct->children()[1]->value->int);
            if ($maxRank != self::$maxRank) {
                self::$maxRank = $maxRank;
                Log::info("Max-Rank changed to $maxRank.");
            }

            $recordsXmlArray = $responseData->params->param->value->struct->children()[3]->value->array->data->value;
            $records = collect();

            foreach ($recordsXmlArray as $xmlRecord) {
                $record = collect();
                $record->login = sprintf('%s', $xmlRecord->struct->member[0]->value->string);
                $record->nickname = sprintf('%s', $xmlRecord->struct->member[1]->value->string);
                $record->score = intval($xmlRecord->struct->member[2]->value->int);
                $record->rank = intval($xmlRecord->struct->member[3]->value->int);
                $record->max_rank = intval($xmlRecord->struct->member[4]->value->int);
                $record->checkpoints = sprintf('%s', $xmlRecord->struct->member[5]->value->string);

                $records->push($record);
            }

            if ($records->count() > 0) {
                //Wipe all dedis for current map
                DB::table('dedi-records')
                    ->where('Map', '=', $map->id)
                    ->where('New', '=', 0)
                    ->delete();

                $newRecordsPlayerIds = DB::table('dedi-records')
                    ->where('Map', '=', $map->id)
                    ->where('New', '=', 1)
                    ->pluck('Player');

                $insert = $records->map(function ($record) use ($map, $newRecordsPlayerIds) {
                    DB::table('players')->updateOrInsert(['Login' => $record->login], [
                        'NickName' => $record->nickname,
                        'MaxRank' => $record->max_rank,
                    ]);

                    $player = player($record->login);

                    if ($newRecordsPlayerIds->contains('', $player->id)) {
                        return null;
                    }

                    return [
                        'Map' => $map->id,
                        'Player' => $player->id,
                        'Score' => $record->score,
                        'Rank' => $record->rank,
                        'Checkpoints' => $record->checkpoints,
                    ];
                })->filter();

                DB::table('dedi-records')->insert($insert->toArray());
            }

            Log::write("Loaded records for map $map [" . $map->uid . ']');
            Dedimania::sendUpdatedDedis();
        });

        return null;
    }

    /**
     * Update connected players
     *
     * @param Map $map
     * @param bool $isTimeAttack
     */
    static function updateServerPlayers(Map $map, bool $isTimeAttack)
    {
        if (Dedimania::isOfflineMode()) {
            return;
        }

        Log::write(sprintf('updateServerPlayers(%s)', $map));

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.UpdateServerPlayers');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', self::getSessionKey());

        //struct SrvInfo
        self::paramAddStruct($params->addChild('param'), [
            'SrvName' => Server::getServerName(),
            'Comment' => Server::getServerComment(),
            'Private' => Server::getServerPassword() ? 1 : 0,
            'NumPlayers' => onlinePlayers()->where('spectator_status', 0)->count(),
            'MaxPlayers' => Server::getMaxPlayers()['CurrentValue'],
            'NumSpecs' => onlinePlayers()->where('spectator_status', '>', 0)->count(),
            'MaxSpecs' => Server::getMaxSpectators()['CurrentValue'],
        ]);

        //struct votesInfo
        self::paramAddStruct($params->addChild('param'), [
            'UId' => $map->uid,
            'GameMode' => $isTimeAttack ? 'TA' : 'Rounds',
        ]);

        //array Players (array of struct: {'Login': string, 'IsSpec': boolean, 'Vote': int (-1 = unchanged)})
        $players = onlinePlayers()->map(function (Player $player) {
            return [
                'Login' => $player->Login,
                'IsSpec' => $player->isSpectator() ? 1 : 0,
                'Vote' => -1,
            ];
        });

        self::paramAddArray($params->addChild('param'), $players->toArray());
        self::postAsync($xml, function ($data) {
            if ($data && !isset($data->params->param->value->boolean)) {
                Log::write('Failed to report connected players. Trying again in 5 minutes.');
            }
        });
    }

    /**
     * Send new records
     *
     * @param Map $map
     * @param bool $isTimeAttack
     */
    static function setChallengeTimes(Map $map, bool $isTimeAttack)
    {
        $mpMap = Server::getCurrentMapInfo();
        $newTimes = $map->dedis()->where('New', 1)->get();

        if ($newTimes->count() == 0) {
            //No new records
            Log::write('No records made.');

            return;
        }

        Log::write(sprintf('setChallengeTimes(%s) New records: %d', $map, $newTimes->count()));

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.SetChallengeTimes');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('value', self::getSessionKey());

        //MapInfo: struct {'uid': string, 'Name': string, 'Environment': string, 'Author': string, 'NbCheckpoints': int, 'NbLaps': int} from GetCurrentChallengeInfo
        self::paramAddStruct($params->addChild('param'), [
            'UId' => $mpMap->uId,
            'Name' => $mpMap->name,
            'Environment' => $mpMap->environnement,
            'Author' => $mpMap->author,
            'NbCheckpoints' => $mpMap->nbCheckpoints,
            'NbLaps' => $mpMap->nbLaps,
        ]);

        //string GameMode
        $params->addChild('param')->addChild('value', $isTimeAttack ? 'TA' : 'Rounds');

        Log::write('New Times:', isVerbose());
        Log::write($newTimes->toJson(), isVerbose());

        //Times: array of struct {'Login': string, 'Best': int, 'Checks': string (list of int, comma separated)}:
        $times = $params->addChild('param')->addChild('array')->addChild('data')->addChild('value');

        $sortedScores = $newTimes->sortBy('Score');

        foreach ($sortedScores as $dedi) {
            if ($dedi->Rank > 100) {
                continue;
            }

            Log::write(sprintf('Add %s\'s time %s', $dedi->player, formatScore($dedi->Score)), isVerbose());

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

        $bestRecord = $sortedScores->first();

        if (!file_exists(cacheDir('vreplays/' . $bestRecord->player->Login . '_' . $map->uid))) {
            Log::error('Missing v-replay for ' . $bestRecord->player->Login . '_' . $map->uid . ', dedis not saved.');
            return;
        }

        $VReplay = file_get_contents(cacheDir('vreplays/' . $bestRecord->player->Login . '_' . $map->uid));
        $VReplayChecks = $bestRecord->Checkpoints;
        $Top1GReplay = '';

        try {
            //Check if there is top1 dedi
            if ($bestRecord->Rank == 1) {
                $Top1GReplay = File::get(ghost(DB::table('dedi-records')->where('Player', '=', $bestRecord->player->id)->where('Map', '=', $map->id)->first()->ghost_replay));

                if ($Top1GReplay == null) {
                    Log::error('Failed to get ghost replay for player ' . $bestRecord->player);
                }
            }

            //Add replays
            self::paramAddStruct($params->addChild('param'), [
                'VReplay' => $VReplay,
                'VReplayChecks' => $VReplayChecks,
                'Top1GReplay' => $Top1GReplay,
            ]);

            if (Dedimania::isOfflineMode()) {
                if (!is_dir(cacheDir('offline_dedis'))) {
                    File::makeDir(cacheDir('offline_dedis'));
                }

                Cache::put(time(), $xml->asXML());
            }

            //Send the request
            self::postAsync($xml, function (SimpleXMLElement $data) {
                Log::info('New Dedis saved.');
            });
        } catch (Exception $e) {
            Log::errorWithCause('Failed to save dedis', $e);
        }

        $map->dedis()->where('New', 1)->update(['New' => 0]);
    }

    /**
     * dedimania.PlayerConnect(string SessionId, string Login, string Nickname, string Path, boolean IsSpec).
     * Return struct {'Login': string, 'MaxRank': int, 'Banned': boolean, 'OptionsEnabled': boolean, 'ToolOption': string}, where:
     * . MaxRank: max rank for player records,
     * . Banned: ban status on Dedimania for the player,
     * . OptionsEnabled: true if tool options can be stored for the player,
     * . ToolOption: optional value stored for the player by the used tool (can usually be config/layout values, and storable only if player has OptionsEnabled).
     *
     * @param Player $player
     * @return null
     */
    public static function playerConnect(Player $player)
    {
        if (Dedimania::isOfflineMode()) {
            return null;
        }

        if (isVerbose()) {
            Log::write(sprintf('playerConnect(%s)', $player), true);
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'dedimania.PlayerConnect');
        $params = $xml->addChild('params');

        //string SessionId
        $params->addChild('param')->addChild('string', self::getSessionKey());

        //string Login
        $params->addChild('param')->addChild('string', $player->Login);

        //string Nickname
        $params->addChild('param')->addChild('string', $player);

        //string Path
        $params->addChild('param')->addChild('string', Server::getDetailedPlayerInfo(config('dedimania.login'))->path);

        //boolean IsSpec
        $params->addChild('param')->addChild('boolean', $player->isSpectator() ? 1 : 0);

        self::postAsync($xml, function (SimpleXMLElement $responseData) use ($player) {
            if (isset($responseData->params->param->value->struct->member) && $responseData->params->param->value->struct->member[0]->value->string == $player->Login) {
                $playerData = $responseData->params->param->value->struct;

                $player->update([
                    'MaxRank' => (int)$playerData->member[1]->value->string,
                    'Banned' => ($playerData->member[2]->value->int != "0"),
                ]);

                if ($player->Banned) {
                    warningMessage($player, ' is banned from dedimania.')->sendAll();
                }
            }
        });

        return null;
    }


    /**
     * Convert array to struct and attach it to given param
     *
     * @param SimpleXMLElement $param
     * @param array $data
     */
    protected static function paramAddStruct(SimpleXMLElement $param, array $data)
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
    protected static function paramAddArray(SimpleXMLElement $param, array $data)
    {
        $array = $param->addChild('array');
        $xmlData = $array->addChild('data');

        foreach ($data as $key => $value) {

            if (isVerbose()) {
                if (gettype($value) == 'array') {
                    Log::write(sprintf('paramAddArray %s => [%s]', $key, implode(', ', $value)), isDebug());
                } else {
                    Log::write(sprintf('paramAddArray %s => %s', $key, $value), isDebug());
                }
            }

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
     * @return SimpleXMLElement|null
     */
    private static function post(SimpleXMLElement $xml): ?SimpleXMLElement
    {
        try {
            $response = RestClient::post('http://dedimania.net:8082/Dedimania', [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF8',
                    'Accept-Encoding' => 'gzip',
                ],
                'decode_content' => 'gzip',
                'body' => $xml->asXML()
            ]);
        } catch (RequestException $e) {
            Log::errorWithCause('DedimaniaAp::post failed', $e);
            return null;
        }

        if ($response->getStatusCode() != 200) {
            Log::warning("Connection to dedimania failed: " . $response->getReasonPhrase());

            return null;
        }

        $data = $response->getBody()->getContents();

        try {
            return new SimpleXMLElement($data);
        } catch (Exception $e) {
            return null;
        }
    }

    private static function postAsync(SimpleXMLElement $xml, $success = null, $fail = null)
    {
        $promise = RestClient::postAsync('http://dedimania.net:8082/Dedimania', [
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF8',
                'Accept-Encoding' => 'gzip',
            ],
            'decode_content' => 'gzip',
            'body' => $xml->asXML()
        ]);

        $promise->then(function (ResponseInterface $response) use ($success) {
            if (!is_null($success)) {
                $success(new SimpleXMLElement($response->getBody()));
            }
        }, function (RequestException $e) use ($fail) {
            Log::warningWithCause('Request failed', $e);
            if (!is_null($fail)) {
                $fail();
            }
        });
    }

    protected static function getSessionKey(): ?string
    {
        if (File::exists(cacheDir('dedimania.session'))) {
            $session = File::get(cacheDir('dedimania.session'));
            $data = json_decode($session);

            return $data->key;
        }

        return null;
    }

    protected static function setSessionKey(string $sessionKey): bool
    {
        $data = [
            'key' => $sessionKey,
            'last_updated' => Carbon::now()->toDateTimeString(),
        ];

        //Return true if file was created
        return File::put(cacheDir('dedimania.session'), json_encode($data));
    }

    protected static function touchSessionKey(): bool
    {
        if (File::exists(cacheDir('dedimania.session'))) {
            return self::setSessionKey(self::getSessionKey());
        }

        return false;
    }

    protected static function getSessionLastUpdated(): ?Carbon
    {
        if (File::exists(cacheDir('dedimania.session'))) {
            $session = File::get(cacheDir('dedimania.session'));
            $data = json_decode($session);

            return new Carbon($data->last_updated);
        }

        return null;
    }
}

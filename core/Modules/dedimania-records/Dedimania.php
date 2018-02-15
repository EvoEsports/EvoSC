<?php

use esc\classes\Config;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\RestClient;
use esc\controllers\ServerController;
use esc\models\Map;

class Dedimania
{
    private static $sessionId;

    public function __construct()
    {
        $this->authenticateAndValidateAccount();

        Hook::add('BeginMap', 'Dedimania::beginMap');
    }

    public static function beginMap(Map $map)
    {
        echo "Check dedis\n";

        var_dump(self::call('dedimania.GetChallengeInfo', [
            'UId' => $map->UId
        ]));
    }

    private function authenticateAndValidateAccount()
    {
        $response = Dedimania::call('dedimania.OpenSession', [
            'Game' => 'TM2',
            'Login' => Config::get('dedimania.login'),
            'Code' => Config::get('dedimania.key'),
            'Tool' => 'EvoSC',
            'Version' => '0.6.1',
            'Packmask' => 'Stadium',
            'ServerVersion' => ServerController::getRpc()->getVersion()->version,
            'ServerBuild' => ServerController::getRpc()->getVersion()->build,
            'Path' => ServerController::getRpc()->getDetailedPlayerInfo(Config::get('dedimania.login'))->path
        ]);

        self::$sessionId = (string)$response->params->param->value->array->data->value->array->data->value->struct->member->value->string;
    }

    /**
     * Call a method on dedimania server
     * See documentation at http://dedimania.net:8082/Dedimania
     * @param string $method
     * @param array|null $parameters
     * @return null|SimpleXMLElement
     */
    public static function call(string $method, array $parameters = null): ?SimpleXMLElement
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

        return new SimpleXMLElement($response->getBody());
    }
}
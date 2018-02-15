<?php

use esc\classes\Log;
use esc\classes\RestClient;

class Dedimania
{
    public function __construct()
    {
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
        $methodCall = $xml->addChild('methodCall');
        $methodCall->addChild('methodName', $method);

        if ($parameters) {
            $params = $methodCall->addChild('params');
            foreach ($parameters as $param) {
                $params->addChild('param', $param);
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
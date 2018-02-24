<?php

namespace esc\Classes;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class RestClient
{
    public static $serverName;
    public static $client;

    public static function init(string $serverName)
    {
        self::$client = new Client();

        self::$serverName = stripStyle(stripColors($serverName));
    }

    public static function getClient(): Client
    {
        return self::getClient();
    }

    public static function get(string $url, array $options = null): Response
    {
        return self::$client->request('GET', $url, self::addUserAgent($options));
    }

    public static function post(string $url, array $options = null): Response
    {
        return self::$client->request('POST', $url, self::addUserAgent($options));
    }

    private static function addUserAgent(array $options = null): array
    {
        if (!$options) {
            $options = [];
        }

        if (!array_key_exists('headers', $options)) {
            $options['headers'] = [];
        }

        $options['headers']['User-Agent'] = sprintf('EvolutionServerController/0.5 (SERVER %s) PHP/7.2', self::$serverName ?: 'unknown');

        return $options;
    }
}
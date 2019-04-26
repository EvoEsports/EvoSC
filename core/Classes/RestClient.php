<?php

namespace esc\Classes;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

/**
 * Class RestClient
 *
 * REST-Client for get/post-requests.
 *
 * @see     http://docs.guzzlephp.org/en/stable/request-options.html
 *
 * @package esc\Classes
 */
class RestClient
{
    public static $serverName;

    /**
     * @var Client
     */
    public static $client;

    /**
     * Initialize the client
     *
     * @param string $serverName
     */
    public static function init(string $serverName)
    {
        self::$client = new Client();

        self::$serverName = stripStyle(stripColors($serverName));
    }

    /**
     * Get the client-instance.
     *
     * @return \GuzzleHttp\Client
     */
    public static function getClient(): Client
    {
        return self::getClient();
    }

    /**
     * Create a get-request.
     *
     * @param string     $url
     * @param array|null $options
     *
     * @return \GuzzleHttp\Psr7\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function get(string $url, array $options = null): Response
    {
        Log::logAddLine('RestClient', 'Requesting GET: ' . $url);

        return self::$client->request('GET', $url, self::addUserAgent($options));
    }

    /**
     * Create a post-request.
     *
     * @param string     $url
     * @param array|null $options
     *
     * @return \GuzzleHttp\Psr7\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function post(string $url, array $options = null): Response
    {
        Log::logAddLine('RestClient', 'Requesting GET: ' . $url . ' with options: ' . json_encode($options));

        return self::$client->request('POST', $url, self::addUserAgent($options));
    }

    //Add user-agent to current options.
    private static function addUserAgent(array $options = null): array
    {
        if (!$options) {
            $options = [];
        }

        if (!array_key_exists('headers', $options)) {
            $options['headers'] = [];
        }

        $options['headers']['User-Agent'] = sprintf('EvoSC/%s PHP/7.2', getEscVersion());

        return $options;
    }
}
<?php

namespace esc\Classes;


use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;

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
     * @return Client
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
     * @return Response
     * @throws GuzzleException
     */
    public static function get(string $url, array $options = null): Response
    {
        if (isDebug()) {
            Log::write('RestClient', 'GET: ' . $url, isDebug());
        }

        return self::$client->request('GET', $url, self::addUserAgent($options));
    }

    /**
     * Create a post-request.
     *
     * @param string     $url
     * @param array|null $options
     *
     * @return Response
     * @throws GuzzleException
     */
    public static function post(string $url, array $options = null): Response
    {
        if (isDebug()) {
            Log::write('RestClient', 'POST: ' . $url . ' with options: ' . json_encode($options),
                isDebug());
        }

        return self::$client->request('POST', $url, self::addUserAgent($options));
    }

    /**
     * Create a put-request.
     *
     * @param string     $url
     * @param array|null $options
     *
     * @return Response
     * @throws GuzzleException
     */
    public static function put(string $url, array $options = null): Response
    {
        if (isDebug()) {
            Log::write('RestClient', 'PUT: ' . $url . ' with options: ' . json_encode($options),
                isDebug());
        }

        return self::$client->request('PUT', $url, self::addUserAgent($options));
    }

    //Add user-agent to current options.

    /**
     * @param array|null $options
     *
     * @return array
     */
    private static function addUserAgent(array $options = null): array
    {
        if (!$options) {
            $options = [];
        }

        if (!array_key_exists('headers', $options)) {
            $options['headers'] = [];
        }

        $options[RequestOptions::VERIFY] = CaBundle::getSystemCaRootBundlePath();
        $options['headers']['User-Agent'] = sprintf('EvoSC/%s PHP/7.2', getEscVersion());

        return $options;
    }
}
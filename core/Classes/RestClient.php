<?php

namespace EvoSC\Classes;


use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;

/**
 * Class RestClient
 *
 * REST-Client for get/post-requests.
 *
 * @see     http://docs.guzzlephp.org/en/stable/request-options.html
 *
 * @package EvoSC\Classes
 */
class RestClient
{
    public static $serverName;

    /**
     * @var Client
     */
    private static Client $client;

    private static CurlMultiHandler $curl;

    /**
     * Initialize the client
     *
     * @param string $serverName
     */
    public static function init(string $serverName)
    {
        self::$curl = new CurlMultiHandler();
        $handler = HandlerStack::create(self::$curl);

        self::$client = new Client(['handler' => $handler]);
        self::$serverName = stripAll($serverName);
    }

    /**
     * Get the client-instance.
     *
     * @return Client
     */
    public static function getClient(): Client
    {
        return self::$client;
    }

    public static function curlTick()
    {
        self::$curl->tick();
    }

    /**
     * Create a get-request.
     *
     * @param string $url
     * @param array|null $options
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function get(string $url, array $options = null): \Psr\Http\Message\ResponseInterface
    {
        Log::write('GET: ' . $url, isVerbose());

        return self::$client->request('GET', $url, self::addUserAgentAndDefaultTimeout($options));
    }

    /**
     * @param string $url
     * @param array|null $options
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function getAsync(string $url, array $options = null)
    {
        Log::write('ASYNC GET: ' . $url, isVerbose());

        return self::$client->getAsync($url, self::addUserAgentAndDefaultTimeout($options));
    }

    /**
     * Create a post-request.
     *
     * @param string $url
     * @param array|null $options
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function post(string $url, array $options = null): \Psr\Http\Message\ResponseInterface
    {
        Log::write('POST: ' . $url, isVerbose());

        return self::$client->request('POST', $url, self::addUserAgentAndDefaultTimeout($options));
    }

    /**
     * @param string $url
     * @param array|null $options
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function postAsync(string $url, array $options = null)
    {
        Log::write('ASYNC POST: ' . $url, isVerbose());

        return self::$client->postAsync($url, self::addUserAgentAndDefaultTimeout($options));
    }

    /**
     * @param array|null $options
     *
     * @return array
     */
    private static function addUserAgentAndDefaultTimeout(array $options = null): array
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
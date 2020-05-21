<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\Log;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ControllerInterface;
use Psr\Http\Message\ResponseInterface;

class UpdateController implements ControllerInterface
{
    private static bool $updateAvailable = false;

    public static function init()
    {
        self::checkForUpdates();
    }

    public static function start(string $mode, bool $isBoot)
    {
    }

    public static function checkForUpdates()
    {
        if (!self::$updateAvailable) {
            $promise = RestClient::getAsync('https://evotm.com/api/evosc/version?branch=develop');

            $promise->then(function (ResponseInterface $response) {
                if ($response->getStatusCode() == 200) {
                    $latestVersion = $response->getBody()->getContents();

                    if ($latestVersion != '-1' && $latestVersion > getEscVersion()) {
                        Log::cyan('EvoSC update available.');
                        self::$updateAvailable = true;
                    }
                }
            });
        }

        Timer::create('evosc_update_checker', [self::class, 'checkForUpdates'], '1m');
    }
}
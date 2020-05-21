<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\Player;
use Psr\Http\Message\ResponseInterface;
use ZipArchive;

class UpdateController implements ControllerInterface
{
    private static bool $updateAvailable = false;

    public static function init()
    {
    }

    public static function start(string $mode, bool $isBoot)
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);

        ChatCommand::add('//update-evosc', [self::class, 'updateEvoSC'], 'Updates EvoSC and restarts it.', 'ma');

        self::checkForUpdates();
    }

    public static function playerConnect(Player $player)
    {
        if (self::$updateAvailable && $player->hasAccess('ma')) {
            infoMessage('There is an update available for ', secondary('EvoSC'), '. Type ', secondary('//update-evosc'), ' to update.')->send($player);
        }
    }

    public static function updateEvoSC(Player $player, $cmd)
    {
        infoMessage('Updating ', secondary('EvoSC'))->send($player);

        $promise = RestClient::getAsync('https://evotm.com/api/evosc/latest?branch=develop');

        $promise->then(function (ResponseInterface $response) use ($player) {
            if ($response->getStatusCode() == 200) {
                file_put_contents(coreDir('../update.zip'), $response->getBody());

                $zip = new ZipArchive;
                $res = $zip->open(coreDir('../update.zip'));
                if ($res === TRUE) {
                    $zip->extractTo('.');
                    $zip->close();

                    infoMessage('EvoSC successfully updated.')->send($player);
                    restart_evosc();
                } else {
                    warningMessage('Failed to update EvoSC.')->send($player);
                }
            }
        });
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
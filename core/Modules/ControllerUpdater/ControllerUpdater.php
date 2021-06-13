<?php


namespace EvoSC\Modules\ControllerUpdater;


use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Template;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use Psr\Http\Message\ResponseInterface;
use ZipArchive;

class ControllerUpdater extends Module implements ModuleInterface
{
    private static bool $updateAvailable = false;
    private static string $latestVersion;

    public static function start(string $mode, bool $isBoot = false)
    {
        return;

        self::$latestVersion = getEvoSCVersion();

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);

        ManiaLinkEvent::add('evosc.update', [self::class, 'mleUpdate'], 'ma');

        self::checkForUpdates();
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        if (self::$updateAvailable) {
            if ($player->hasAccess('ma')) {
                Template::show($player, 'ControllerUpdater.widget', ['latest_version' => self::$latestVersion]);
            }
        }
    }

    public static function mleUpdate(Player $player)
    {
        Template::show($player, 'ControllerUpdater.update', ['message' => 'Downloading EvoSC v' . self::$latestVersion]);

        try {
            $promise = RestClient::getAsync('https://evotm.com/api/evosc/latest?branch=' . config('evosc.release'));

            $promise->then(function (ResponseInterface $response) use ($player) {
                if ($response->getStatusCode() == 200) {
                    file_put_contents(coreDir('../update.zip'), $response->getBody());

                    Template::show($player, 'ControllerUpdater.update', ['message' => 'Extracting update...']);

                    $zip = new ZipArchive;
                    $res = $zip->open(coreDir('../update.zip'));
                    if ($res === TRUE) {
                        $zip->extractTo('.');
                        $zip->close();

                        Template::show($player, 'ControllerUpdater.update', ['message' => 'Update installed, restarting...']);
                        usleep(100000);
                        unlink(coreDir('../update.zip'));
                        restart_evosc();
                    } else {
                        dangerMessage('Failed to update ', secondary('EvoSC'))->send($player);
                    }
                }
            });
        } catch (\Exception $e) {
            Log::errorWithCause('Failed to update EvoSC', $e);
        }
    }

    /**
     *
     */
    public static function checkForUpdates()
    {
        if (!self::$updateAvailable) {
            try {
                $promise = RestClient::getAsync('https://evotm.com/api/evosc/version?branch=' . config('evosc.release'));

                $promise->then(function (ResponseInterface $response) {
                    if ($response->getStatusCode() == 200) {
                        $latestVersion = $response->getBody()->getContents();

                        if ($latestVersion != '-1' && version_compare($latestVersion, getEvoSCVersion(), 'gt')) {
                            self::$latestVersion = $latestVersion;
                            self::$updateAvailable = true;
                            Log::cyan('EvoSC update available.');

                            foreach (accessPlayers('ma') as $player) {
                                Template::show($player, 'ControllerUpdater.widget', ['latest_version' => self::$latestVersion]);
                            }
                        }
                    }
                });
            } catch (\Exception $e) {
                Log::errorWithCause('Failed to check for EvoSC update', $e);
            }
        }

        Timer::create('evosc_update_checker', [self::class, 'checkForUpdates'], '1h');
    }
}

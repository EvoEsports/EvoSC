<?php


namespace EvoSC\Modules\MOTD;


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

class MOTD extends Module implements ModuleInterface
{
    private static string $messageOfTheDay = '';
    private static string $motdHash = '';

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (config('motd.message') == '' && config('motd.url') == '') {
            Log::error('MOTD enabled but no message or URL set! Not starting module.');
            return;
        }

        self::setMOTD((string)config('motd.message'));

        if (config('motd.url') != '') {
            Timer::create('update_motd', [self::class, 'updateMessage'], '30m', true);
            self::updateMessage();
        }

        Hook::add('PlayerConnect', [self::class, 'playerConnect']);

        ManiaLinkEvent::add('motd.dnsa', [self::class, 'mleDoNotShowAgain']);
    }

    /**
     * @param Player $player
     */
    public static function mleDoNotShowAgain(Player $player)
    {
        $player->setSetting('motd_hash', md5(self::$messageOfTheDay));
    }

    /**
     *
     */
    public static function updateMessage()
    {
        RestClient::getAsync(config('motd.url'))->then(function (ResponseInterface $response) {
            self::$messageOfTheDay = $response->getBody();
        });
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function playerConnect(Player $player)
    {
        if ($player->setting('motd_hash') == self::$motdHash) {
            return;
        }

        Template::show($player, 'MOTD.motd', ['motd' => self::$messageOfTheDay]);
    }

    /**
     * @param $string
     */
    private static function setMOTD($string)
    {
        self::$messageOfTheDay = $string;
        self::$motdHash = md5($string);
    }
}
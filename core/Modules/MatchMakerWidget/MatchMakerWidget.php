<?php


namespace EvoSC\Modules\MatchMakerWidget;


use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;

class MatchMakerWidget extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        AccessRight::add('match_maker', 'Control matches and view the admin panel for it.');

        ManiaLinkEvent::add('toggle_horns', [self::class, 'mleToggleHorns'], 'match_maker');
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function showWidget(Player $player)
    {
        $hornsEnabled = !Server::areHornsDisabled();

        Template::show($player, 'MatchMakerWidget.widget', compact('hornsEnabled'));
    }

    /**
     * @param Player $player
     */
    public static function mleToggleHorns(Player $player)
    {
        if (Server::areHornsDisabled()) {
            Server::disableHorns(false);
            successMessage($player, ' enabled horns, happy honking!')->sendAll();
        } else {
            Server::disableHorns(true);
            dangerMessage($player, ' disabled horns.')->sendAll();
        }
    }
}
<?php


namespace EvoSC\Modules\TestModule;


use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Controllers\TemplateController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\FloatingNickNames\FloatingNickNames;
use EvoSC\Modules\HackMe\HackMe;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\LiveRankings\LiveRankings;
use EvoSC\Modules\MatchMakerWidget\MatchMakerWidget;
use EvoSC\Modules\ScoreTable\ScoreTable;
use EvoSC\Modules\SocialMedia\SocialMedia;
use EvoSC\Modules\TeamInfo\TeamInfo;
use Illuminate\Support\Collection;

class TestModule extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, $isBoot = false)
    {
        InputSetup::add('test_stuff', 'Trigger TestModule::testStuff', [self::class, 'testStuff'], 'X', 'ma');

        ManiaLinkEvent::add('asdf', [self::class, 'sendaction_for_Controller']);
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();
        Server::triggerModeScriptEventArray('Trackmania.GetScores');
    }

    public static function sendTestManialink(Player $player)
    {
        Template::show($player, 'TestModule.test');
    }

    public static function sendaction_for_Controller(Player $player, Collection $formData = null)
    {
        var_dump($player->Login, $formData);
    }
}
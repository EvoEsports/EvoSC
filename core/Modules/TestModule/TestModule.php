<?php


namespace EvoSC\Modules\TestModule;


use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\TemplateController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use EvoSC\Modules\AddTime\AddTime;
use EvoSC\Modules\CountDown\CountDown;
use EvoSC\Modules\CpDiffs\CpDiffs;
use EvoSC\Modules\EvoCupInfo\EvoCupInfo;
use EvoSC\Modules\InfoMessages\InfoMessages;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\LiveRankings\LiveRankings;
use EvoSC\Modules\MatchMakerWidget\MatchMakerWidget;
use EvoSC\Modules\MxDetails\MxDetails;
use EvoSC\Modules\ScoreTable\ScoreTable;
use EvoSC\Modules\Symbols\Symbols;
use EvoSC\Modules\Votes\Votes;
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
        LiveRankings::playerConnect($player);
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
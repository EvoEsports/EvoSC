<?php


namespace EvoSC\Modules\TestModule;


use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\TemplateController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\AlterUI\AlterUI;
use EvoSC\Modules\CpDiffs\CpDiffs;
use EvoSC\Modules\EvoDonate\EvoDonate;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\LiveRankings\LiveRankings;
use EvoSC\Modules\LocalRecords\LocalRecords;
use EvoSC\Modules\LocalRecords\LocalsBenchmark;
use EvoSC\Modules\QuickButtons\QuickButtons;
use EvoSC\Modules\ScoreTable\ScoreTable;
use EvoSC\Modules\UIHax\UIHax;
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
        CpDiffs::sendInitialCpDiff($player, MapController::getCurrentMap());
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
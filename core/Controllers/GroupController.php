<?php

namespace esc\Controllers;


use esc\Classes\ChatCommand;
use esc\classes\Database;
use esc\Classes\File;
use esc\Classes\Log;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Models\AccessRight;
use esc\Models\Group;
use esc\Models\Player;
use Illuminate\Database\Schema\Blueprint;

class GroupController
{
    public static function init()
    {
        ChatCommand::add('group', [GroupController::class, 'group'], 'Group commands', '//', 'group');
    }

    public static function createTables()
    {
        $seed = [
            ['id' => 1, 'Name' => 'Masteradmin', 'Protected' => true],
            ['id' => 2, 'Name' => 'Admin', 'Protected' => true],
            ['id' => 3, 'Name' => 'Player', 'Protected' => true],
        ];

        Database::create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Name')->unique();
            $table->boolean('Protected')->default(false);
        }, $seed);
    }

    public static function groupsShow(Player $player)
    {
        Template::hide($player, 'Group - Edit');
        self::displayGroups($player);
    }

    public static function displayGroups(Player $player)
    {
        $groups = Group::all();

        $groupList = Template::toString('groups', compact('groups'));

        Template::show($player, 'components.modal', [
            'id' => 'Groups',
            'width' => 90,
            'height' => count($groups) * 4.5 + 15,
            'content' => $groupList
        ]);
    }
}
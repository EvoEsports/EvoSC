<?php

namespace EvoSC\Modules\GroupManager;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\PlayerController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Group;
use EvoSC\Models\Player;
use EvoSC\Modules\QuickButtons\QuickButtons;

class GroupManager extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        AccessRight::add('group_edit', 'Add/delete/update groups.');

        ChatCommand::add('//groups', [self::class, 'showOverview'], 'Show groups manager', 'group_edit');

        ManiaLinkEvent::add('group.overview', [self::class, 'showOverview'], 'group_edit');
        ManiaLinkEvent::add('group.create', [self::class, 'groupCreate'], 'group_edit');
        ManiaLinkEvent::add('group.delete', [self::class, 'groupDelete'], 'group_edit');
        ManiaLinkEvent::add('group.edit_access', [self::class, 'groupEditAccess'], 'group_edit');
        ManiaLinkEvent::add('group.edit_group', [self::class, 'groupEdit'], 'group_edit');
        ManiaLinkEvent::add('group.update', [self::class, 'groupUpdate'], 'group_edit');
        ManiaLinkEvent::add('group.members', [self::class, 'groupMembers'], 'group_edit');
        ManiaLinkEvent::add('group.member_remove', [self::class, 'groupMemberRemove'], 'group_edit');
        ManiaLinkEvent::add('group.member_add_form', [self::class, 'groupMemberAddForm'], 'group_edit');
        ManiaLinkEvent::add('group.member_add', [self::class, 'groupMemberAdd'], 'group_edit');
        ManiaLinkEvent::add('group.rights_update', [self::class, 'groupAccessRightsUpdate'], 'group_edit');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Group Manager', 'group.overview', 'group_edit');
        }
    }

    public static function sendGroupsInformation(Player $player)
    {
        $groups = DB::table('groups')
            ->select(['id', 'Name as name', 'chat_prefix as icon', 'color'])
            ->get()
            ->keyBy('id');

        Template::show($player, 'GroupManager.update', compact('groups'), false, 20);
    }

    /**
     * @param Player $player
     * @param string $groupId
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function groupEditAccess(Player $player, string $groupId)
    {
        $group = Group::find($groupId);
        $accessRights = AccessRight::all();

        Template::show($player, 'GroupManager.edit_access', compact('group', 'accessRights'));
    }

    /**
     * @param Player $player
     * @param $formData
     */
    public function groupAccessRightsUpdate(Player $player, $formData)
    {
        $groupId = $formData->group_id;
        DB::table('access_right_group')->where('group_id', '=', $groupId)->delete();

        collect($formData)->forget('group_id')->each(function ($value, $key) use ($groupId) {
            if ($value != "0") {
                DB::table('access_right_group')->insert([
                    'group_id' => $groupId,
                    'access_right_id' => DB::table('access-rights')->where('name', '=', $key)->first()->id, //TOD: Remove after July 2020
                    'access_right_name' => $key
                ]);
            }
        });

        self::showOverview($player);
    }

    public static function showOverview(Player $player)
    {

        $groups = Group::all();

        Template::show($player, 'GroupManager.overview', compact('groups'));
    }

    public static function groupCreate(Player $player, $input)
    {
        $groupNameExists = Group::whereName($input)->get()->isNotEmpty();

        if ($groupNameExists) {
            warningMessage('Group name ', secondary($input), ' already taken.')->send($player);

            return;
        }

        $group = Group::create(['Name' => $input]);

        if ($group) {
            infoMessage('Created new group: ', secondary($input))->send($player);
            self::showOverview($player);
        } else {
            warningMessage('Failed to create group: ', secondary($input))->send($player);
        }
    }

    public static function groupUpdate(Player $player, string $groupId, string $prefix, string $color)
    {
        $group = Group::find($groupId);

        if ($group) {
            $group->update([
                'chat_prefix' => $prefix,
                'color' => $color,
            ]);

            self::showOverview($player);
        }
    }

    public static function groupDelete(Player $player, string $groupId)
    {
        $group = Group::find($groupId);

        if ($group) {
            //Move players from group into default-group
            if ($group->player->isNotEmpty()) {
                $group->player()->update(['Group' => 3]);
            }

            //Delete group
            $group->delete();

            self::showOverview($player);
        }
    }

    public static function groupEdit(Player $player, string $groupId)
    {
        $group = Group::find($groupId);

        Template::show($player, 'GroupManager.edit', compact('group'));
    }

    public static function groupMembers(Player $player, string $groupId)
    {
        $group = Group::find($groupId);

        if ($group) {
            $playerCount = $group->player()->count();
            Template::show($player, 'GroupManager.members', compact('group', 'playerCount'));
        }
    }

    public static function groupMemberRemove(Player $player, string $groupId, string $memberLogin)
    {
        $member = Player::find($memberLogin);

        if ($member) {
            Player::whereLogin($memberLogin)->update(['Group' => 3]);
            infoMessage($player, ' removed ', $member, '\'s access rights.')->sendAll();
            self::groupMembers($player, $groupId);
        }
    }

    public static function groupMemberAddForm(Player $player, string $groupId)
    {
        $players = onlinePlayers();
        $playerCount = $players->count();

        Template::show($player, 'GroupManager.add', compact('players', 'groupId', 'playerCount'));
    }

    public static function groupMemberAdd(Player $player, string $groupId, string $playerLogin)
    {
        $newMember = Player::find($playerLogin);

        if (!$newMember) {
            $newMember = Player::create([
                'NickName' => $playerLogin,
                'Login' => $playerLogin,
            ]);
        }

        $group = Group::find(intval($groupId));
        Player::whereLogin($playerLogin)->update(['Group' => $group->id]);
        Hook::fire('GroupChanged', $newMember);
        infoMessage($player->group, ' ', $player, ' changed ', $newMember, '\'s group to ', secondary($group))->sendAll();
    }
}
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
        ChatCommand::add('//groups', [self::class, 'showOverview'], 'Show groups manager', 'group');

        ManiaLinkEvent::add('group.overview', [self::class, 'showOverview'], 'group');
        ManiaLinkEvent::add('group.create', [self::class, 'groupCreate'], 'group');
        ManiaLinkEvent::add('group.delete', [self::class, 'groupDelete'], 'group');
        ManiaLinkEvent::add('group.edit_access', [self::class, 'groupEditAccess'], 'group');
        ManiaLinkEvent::add('group.edit_group', [self::class, 'groupEdit'], 'group');
        ManiaLinkEvent::add('group.update', [self::class, 'groupUpdate'], 'group');
        ManiaLinkEvent::add('group.members', [self::class, 'groupMembers'], 'group');
        ManiaLinkEvent::add('group.member_remove', [self::class, 'groupMemberRemove'], 'group');
        ManiaLinkEvent::add('group.member_add_form', [self::class, 'groupMemberAddForm'], 'group');
        ManiaLinkEvent::add('group.member_add', [self::class, 'groupMemberAdd'], 'group');
        ManiaLinkEvent::add('group.rights_update', [self::class, 'groupRightsUpdate'], 'group');

        AccessRight::createIfMissing('group_edit', 'Add/delete/update groups.');
        AccessRight::createIfMissing('group_change', 'Change player group.');

        if (config('quick-buttons.enabled')) {
            QuickButtons::addButton('', 'Group Manager', 'group.overview', 'group');
        }
    }

    public static function sendGroupsInformation(Player $player)
    {
        $groups = DB::table('groups')
            ->select(['id', 'Name as name', 'chat_prefix as icon', 'color'])
            ->get()
            ->keyBy('id');

        Template::show($player, 'group-manager.update', compact('groups'), false, 20);
    }

    public function groupRightsUpdate(Player $player, $formData)
    {
        $groupId = $formData->group_id;
        DB::table('access_right_group')->where('group_id', '=', $groupId)->delete();

        collect($formData)->forget('group_id')->each(function ($value, $key) use ($groupId) {
            if ($value != "0") {
                DB::table('access_right_group')->insert([
                    'group_id' => $groupId,
                    'access_right_id' => $key
                ]);
            }
        });

        self::showOverview($player);
    }

    public static function showOverview(Player $player)
    {

        $groups = Group::all();

        Template::show($player, 'group-manager.overview', compact('groups'));
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

    public static function groupEditAccess(Player $player, string $groupId)
    {
        $group = Group::find($groupId);
        $accessRights = AccessRight::all();

        Template::show($player, 'group-manager.edit_access', compact('group', 'accessRights'));
    }

    public static function groupEdit(Player $player, string $groupId)
    {
        $group = Group::find($groupId);

        Template::show($player, 'group-manager.edit', compact('group'));
    }

    public static function groupMembers(Player $player, string $groupId)
    {
        $group = Group::find($groupId);

        if ($group) {
            $playerCount = $group->player()->count();
            Template::show($player, 'group-manager.members', compact('group', 'playerCount'));
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

        Template::show($player, 'group-manager.add', compact('players', 'groupId', 'playerCount'));
    }

    public static function groupMemberAdd(Player $player, string $groupId, string $playerLogin)
    {
        $newMember = Player::find($playerLogin);

        if (!$newMember) {
            $newMember = new Player();

            $newMember->NickName = $playerLogin;
            $newMember->Login = $playerLogin;

            $newMember->save();
        }

        $group = Group::find($groupId);

        if ($newMember) {
            Player::whereLogin($playerLogin)->update(['Group' => $group->id]);
            Hook::fire('GroupChanged', $newMember);
            PlayerController::putPlayer(Player::whereLogin($playerLogin)->first());

            if ($newMember->group->id == 3) {
                infoMessage($player->group, ' ', $player, ' added ', $newMember, ' to group ', secondary($group))->sendAll();
            } else {
                infoMessage($player->group, ' ', $player, ' changed ', $newMember, '\'s group to ', secondary($group))->sendAll();
            }
        }
    }
}
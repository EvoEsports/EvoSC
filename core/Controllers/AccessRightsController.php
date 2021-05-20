<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\DB;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;

class AccessRightsController implements ControllerInterface
{
    /**
     * Method called on controller boot.
     */
    public static function init()
    {
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot)
    {
        $groupAccess = collect();

        onlinePlayers()->each(function (Player $player) use ($groupAccess) {
            if (!$groupAccess->has($player->group->id)) {
                if ($player->group->unrestricted) {
                    $groupAccess->put($player->group->id, AccessRight::all()->pluck('name'));
                } else {
                    $groupAccess->put($player->group->id, $player->group->accessRights()->pluck('name'));
                }
            }

            $manialink = '
<manialink name="EvoSC:access-rights" id="access-rights" version="3">
    <script><!--
        main() {
            declare Text[] ESC_Access_rights for This;
            ESC_Access_rights.fromjson("""' . $groupAccess->get($player->group->id)->toJson() . '""");
        }
        --></script>
</manialink>
        ';

            Server::sendDisplayManialinkPage($player->Login, $manialink, 0, false, true);
        });


        Server::executeMulticall();
    }
}
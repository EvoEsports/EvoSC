<?php


namespace EvoSC\Controllers;


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
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot)
    {
        $groupAccess = collect();

        onlinePlayers()->each(function (Player $player) use ($groupAccess) {
            if (!$groupAccess->has($player->Group)) {
                if ($player->Group == 1) {
                    $groupAccess->put(1, AccessRight::all()->pluck('name'));
                } else {
                    $groupAccess->put($player->Group, $player->group->accessRights()->pluck('name'));
                }
            }

            $manialink = '
<manialink name="ESC:access-rights" id="access-rights" version="3">
    <script><!--
        main() {
            declare Text[] ESC_Access_rights for This;
            ESC_Access_rights.fromjson("""'.$groupAccess->get($player->Group)->toJson().'""");
        }
        --></script>
</manialink>
        ';

            Server::sendDisplayManialinkPage($player->Login, $manialink, 0, false, true);
        });


        Server::executeMulticall();
    }
}
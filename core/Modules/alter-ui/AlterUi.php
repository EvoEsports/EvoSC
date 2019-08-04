<?php

namespace esc\Modules;

use esc\Classes\Server;

class AlterUi
{
    public function __construct()
    {
        self::setUiProperties();
    }

    //https://github.com/maniaplanet/script-xmlrpc/blob/master/XmlRpcListing.md#trackmaniauisetproperties
    public static function setUiProperties()
    {
        $properties = '
 	<!--
 	  Each node is optional and can be omitted.
 	  If it\'s the case then the previous value will be kept.
 	-->
 	<ui_properties>
 		<!-- The map name and author displayed in the top right of the screen when viewing the scores table -->
 		<map_info visible="false" pos="-160. 80. 150." />
 		<!-- Information about live envent displayed in the top right of the screen -->
 		<live_info visible="false" pos="-159. 84. 5." />
 		<!-- Information about the spectated player displayed in the bottom of the screen -->
 		<spectator_info visible="true" pos="0. -68. 5." />
 		<!-- Only visible in solo modes, it hides the medal/ghost selection UI -->
 		<opponents_info visible="true" />
 		<!--
 			The server chat displayed on the bottom left of the screen
 			The offset values range from 0. to -3.2 for x and from 0. to 1.8 for y
 			The linecount property must be between 0 and 40
 		-->
 		<chat visible="true" offset="0. 0." linecount="7" />
 		<!-- Time of the players at the current checkpoint displayed at the bottom of the screen -->
 		<checkpoint_list visible="false" pos="48. -52. 5." />
 		<!-- Small scores table displayed at the end of race of the round based modes (Rounds, Cup, ...) on the right of the screen -->
 		<round_scores visible="true" pos="-158.5 40. 150." />
 		<!-- Race time left displayed at the bottom right of the screen -->
 		<countdown visible="true" pos="155 -20 5." />
 		<!-- 3, 2, 1, Go! message displayed on the middle of the screen when spawning -->
 		<go visible="true" />
 		<!-- Current race chrono displayed at the bottom center of the screen -->
 		<chrono visible="false" pos="0. -80. -5." />
 		<!-- Speed and distance raced displayed in the bottom right of the screen -->
 		<speed_and_distance visible="false" pos="137. -69. 5." />
 		<!-- Previous and best times displayed at the bottom right of the screen -->
 		<personal_best_and_rank visible="false" pos="157. -24. 5." />
 		<!-- Current position in the map ranking displayed at the bottom right of the screen -->
 		<position visible="false" pos="150.5 -28. 5." />
 		<!-- Checkpoint time information displayed in the middle of the screen when crossing a checkpoint -->
 		<checkpoint_time visible="true" pos="0. 3. -10." />
 		<!-- The avatar of the last player speaking in the chat displayed above the chat -->
 		<chat_avatar visible="false" />
 		<!-- Warm-up progression displayed on the right of the screen during warm-up -->
 		<warmup visible="true" pos="153. 13. 0." />
 		<!-- Ladder progression box displayed on the top of the screen at the end of the map -->
 		<endmap_ladder_recap visible="false" />
 		<!-- Laps count displayed on the right of the screen on multilaps map -->
 		<multilap_info visible="true" pos="140. 84. 5." />
 		<!-- Player\'s ranking at the latest checkpoint -->
 		<checkpoint_ranking visible="false" pos="0. 84. 5." />
 		<!-- Number of players spectating us displayed at the bottom right of the screen -->
 		<viewers_count visible="true" pos="157. -40. 5." />
 		<!-- Scores table displayed in the middle of the screen -->
 		<scorestable alt_visible="true" visible="true" />
 	</ui_properties>';

        Server::triggerModeScriptEvent('Trackmania.UI.SetProperties', [$properties]);
    }
}
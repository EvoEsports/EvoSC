<?php


namespace EvoSC\Controllers;


use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use Maniaplanet\DedicatedServer\Structures\Tag;

class TeamController implements ControllerInterface
{
    public static function init()
    {
    }

    public static function start(string $mode, bool $isBoot)
    {
//        Server::setForcedClubLinks(self::getClubLink('Evo'), self::getClubLink('Aurora'));
    }

    private static function getClubLink(string $name)
    {
        return <<<EOL
<?xml version="1.0" encoding="utf-8"?>
<club version="1">
  <!-- Name of the team -->
	<name>$name</name>
  
  <!-- Zone of the team (See in game for the zone structure) -->
	<zone>World|France|Ile-de-France</zone>
  
  <!-- City of the team -->
	<city>Paris</city>
  
  <!-- Colors of the team (Used on the spawns, the poles and the UI) -->
	<color primary="0DA" secondary="95D" />
  
  <!-- Emblem of the team (Used on the spawns, the poles and the UI) -->
  <!-- Must be a dds file in BC1/DXT1 with mipmaps of 512x512 pixels-->
<!--	<emblem>http://www.example.com/Emblem_Example.dds</emblem>-->
	
	<!-- Players list -->
	<players>
		<player login="Example2">
			<nickname>Braker</nickname>
			<avatar>https://evotm.com/static/img/logo.png</avatar>
		</player>
	</players>
  
  <!-- Sponsors list -->
  <!-- They'll be displayed on the sides of the screen during the end of the rounds/maps  -->
	<sponsors>
<!--		<sponsor name="Sponsor 1">-->
      <!-- Image of the sponsor -->
      <!-- Any format supported by ManiaPlanet can be used (jpg, png, tga, dds, bik), image ratio 2:1. -->
<!--			<image_2x1>http://www.example.com/Sponsor_1.png</image_2x1>-->
<!--		</sponsor>-->
	</sponsors>
</club>
EOL;
    }
}
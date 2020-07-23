<?php


namespace EvoSC\Classes;


use Maniaplanet\DedicatedServer\Structures\Map;

class MPS_Map extends \stdClass
{
    public int $CheckpointsPerLaps = -1;
    public int $NbLaps = -1;
    public int $DisplayCost = -1;
    public int $LightmapVersion = -1;
    public int $AuthorTime = -1;
    public int $GoldTime = -1;
    public int $SilverTime = -1;
    public int $BronzeTime = -1;
    public bool $IsValidated = false;
    public bool $PasswordProtected = false;
    public string $MapStyle = '';
    public string $MapType = '';
    public string $Mod = '';
    public string $Decoration = '';
    public string $Environment = '';
    public string $PlayerModel = '';
    public string $MapUid = '';
    public string $Comment = '';
    public string $TitleId = '';
    public string $AuthorLogin = '';
    public string $Name = '';
    public string $ClassName = '';
    public string $ClassId = '';
    public string $FileName = '';

    /**
     * Parses a gbx-json for better type-hinting and validation
     *
     * @param $object
     * @return MPS_Map
     */
    public static function fromObject($object): MPS_Map
    {
        $map = new self();

        if ($object instanceof Map) {
            $map->MapUid = $object->uId;
            $map->Name = $object->name;
            $map->FileName = $object->fileName;
            $map->AuthorLogin = $object->author;
            $map->Environment = $object->environnement;
            $map->BronzeTime = $object->bronzeTime;
            $map->SilverTime = $object->silverTime;
            $map->GoldTime = $object->goldTime;
            $map->AuthorTime = $object->authorTime;
            $map->NbLaps = $object->nbLaps;
            $map->CheckpointsPerLaps = $object->nbCheckpoints;
            $map->MapType = $object->mapType;
            $map->MapStyle = $object->mapStyle;
        } else {
            $object->FileName = str_replace("\xEF\xBB\xBF", '', $object->fileName);
            unset($object->fileName);
            foreach ($object as $key => $value) {
                $map->{$key} = $value;
            }
        }

        return $map;
    }
}
<?php


namespace EvoSC\Classes;


class MPS_Map extends \stdClass
{
    public int $CheckpointsPerLaps;
    public int $NbLaps;
    public int $DisplayCost;
    public int $LightmapVersion;
    public int $AuthorTime;
    public int $GoldTime;
    public int $SilverTime;
    public int $BronzeTime;
    public bool $IsValidated;
    public bool $PasswordProtected;
    public string $MapStyle;
    public string $MapType;
    public string $Mod;
    public string $Decoration;
    public string $Environment;
    public string $PlayerModel;
    public string $MapUid;
    public string $Comment;
    public string $TitleId;
    public string $AuthorLogin;
    public string $Name;
    public string $ClassName;
    public string $ClassId;
    public string $FileName;

    /**
     * Parses a gbx-json for better type-hinting and validation
     *
     * @param $object
     * @return MPS_Map
     */
    public static function fromObject($object): MPS_Map
    {
        $object->FileName = str_replace("\xEF\xBB\xBF", '', $object->fileName);
        unset($object->fileName);
        $map = new self();
        foreach ($object as $key => $value) {
            $map->{$key} = $value;
        }
        return $map;
    }
}
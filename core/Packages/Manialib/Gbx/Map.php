<?php

namespace Manialib\Gbx;

use Manialib\Gbx\Map\Dependency;
use Manialib\Gbx\Map\Parser;
use Manialib\Gbx\Map\Thumbnail;

#[\AllowDynamicProperties]
class Map
{
    /**
     * @var string
     */
    protected $exeVersion;
    /**
     * @var string
     */
    protected $exeBuild;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var int
     */
    protected $lightmap;
    /**
     * @var string
     */
    protected $uid;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var string
     */
    protected $author;
    /**
     * @var string
     */
    protected $authorZone;
    /**
     * @var string
     */
    protected $environment;
    /**
     * @var string
     */
    protected $mood;
    /**
     * @var string
     */
    protected $type;
    /**
     * @var string
     */
    protected $mapType;
    /**
     * @var string
     */
    protected $mapStyle;
    /**
     * @var bool
     */
    protected $validated;
    /**
     * @var int
     */
    protected $nbLaps;
    /**
     * @var int
     */
    protected $displayCost;
    /**
     * @var string
     */
    protected $mod;
    /**
     * @var bool
     */
    protected $hasGhostBlocks;
    /**
     * @var string
     */
    protected $playerModel;
    /**
     * @var int
     */
    protected $bronzeMedal;
    /**
     * @var int
     */
    protected $silverMedal;
    /**
     * @var int
     */
    protected $goldMedal;
    /**
     * @var int
     */
    protected $authorTime;
    /**
     * @var int
     */
    protected $authorScore;
    /**
     * @var Dependency[]
     */
    protected $dependencies = array();
    /**
     * @var Thumbnail
     */
    protected $thumbnail;
    /**
     * @var string
     */
    protected $comments;

    /**
     * @param string $filename
     * @return Map
     */
    public static function loadFile($filename)
    {
        return Parser::parseFile($filename);
    }

    /**
     * @param string $string
     * @return Map
     */
    public static function loadString($string)
    {
        return Parser::parseString($string);
    }

    /**
     * @param array $properties
     */
    public function __construct(array $properties)
    {
        foreach ($properties as $key => $value) {
            if (!property_exists($this, $key)) {
                throw new \InvalidArgumentException(sprintf('property %s does not exist', $key));
            }

            if ($key == 'thumbnail') {
                if (!($value instanceof Thumbnail)) {
                    throw new \InvalidArgumentException(sprintf('Thumbnail is not an instance of %s', Thumbnail::class));
                }
            } elseif ($key == 'dependencies') {
                if (!is_array($value)) {
                    throw new \InvalidArgumentException(sprintf('Dependencies must be an array of %s', Dependency::class));
                }
                foreach ($value as $dependency) {
                    if (!($dependency instanceof Dependency)) {
                        throw new \InvalidArgumentException(sprintf('Dependencies must be an array of %s', Dependency::class));
                    }
                }
            }
            $this->$key = $value;
        }
    }

    /**
     * @return string
     */
    public function getExeVersion()
    {
        return $this->exeVersion;
    }

    /**
     * @return string
     */
    public function getExeBuild()
    {
        return $this->exeBuild;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return int
     */
    public function getLightmap()
    {
        return $this->lightmap;
    }

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @return string
     */
    public function getAuthorZone()
    {
        return $this->authorZone;
    }

    /**
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @return string
     */
    public function getMood()
    {
        return $this->mood;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getMapType()
    {
        return $this->mapType;
    }

    /**
     * @return string
     */
    public function getMapStyle()
    {
        return $this->mapStyle;
    }

    /**
     * @return boolean
     */
    public function isValidated()
    {
        return $this->validated;
    }

    /**
     * @return int
     */
    public function getNbLaps()
    {
        return $this->nbLaps;
    }

    /**
     * @return int
     */
    public function getDisplayCost()
    {
        return $this->displayCost;
    }

    /**
     * @return string
     */
    public function getMod()
    {
        return $this->mod;
    }

    /**
     * @return boolean
     */
    public function hasGhostBlocks()
    {
        return $this->hasGhostBlocks;
    }

    /**
     * @return string
     */
    public function getPlayerModel()
    {
        return $this->playerModel;
    }

    /**
     * @return int
     */
    public function getBronzeMedal()
    {
        return $this->bronzeMedal;
    }

    /**
     * @return int
     */
    public function getSilverMedal()
    {
        return $this->silverMedal;
    }

    /**
     * @return int
     */
    public function getGoldMedal()
    {
        return $this->goldMedal;
    }

    /**
     * @return int
     */
    public function getAuthorTime()
    {
        return $this->authorTime;
    }

    /**
     * @return int
     */
    public function getAuthorScore()
    {
        return $this->authorScore;
    }

    /**
     * @return Dependency[]
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * @return Thumbnail
     */
    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    /**
     * @return string
     */
    public function getComments()
    {
        return $this->comments;
    }
}

<?php

namespace Manialib\Gbx\Map;

class Thumbnail
{
    protected $rawData;

    public function __construct($rawData)
    {
        $this->rawData = $rawData;
    }

    public function saveJpg($filename)
    {
        file_put_contents($filename, $this->rawData);
    }

    public function __toString()
    {
        return $this->rawData;
    }
}

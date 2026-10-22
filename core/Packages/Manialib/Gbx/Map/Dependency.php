<?php

namespace Manialib\Gbx\Map;

class Dependency
{
    /**
     * @var string
     */
    protected $file;
    /**
     * @var string
     */
    protected $url;

    /**
     * @param string $file
     */
    public function __construct($file, $url = '')
    {
        $this->file = $file;
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
}

<?php


namespace esc\Classes;


use stdClass;

class Module
{
    protected string $namespace;
    protected string $directory;
    protected stdClass $config;

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     */
    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * @param string $directory
     */
    public function setDirectory(string $directory): void
    {
        $this->directory = $directory;
    }

    /**
     * @return stdClass
     */
    public function getConfig(): stdClass
    {
        return $this->config;
    }

    /**
     * @param stdClass $config
     */
    public function setConfig(stdClass $config): void
    {
        $this->config = $config;
    }


}
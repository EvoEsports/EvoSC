<?php


namespace EvoSC\Classes;


use stdClass;

class Module
{
    protected string $name;
    protected string $namespace;
    protected string $directory;
    protected string $configId;

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
    public function getConfigId(): string
    {
        return $this->configId;
    }

    /**
     * @param stdClass $config
     */
    public function setConfigId(string $configId): void
    {
        $this->configId = $configId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
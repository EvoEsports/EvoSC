<?php


namespace EvoSC\Classes;


use stdClass;

class Module
{
    const PRIORITY_HIGHEST = 4;
    const PRIORITY_HIGH = 3;
    const PRIORITY_NORMAL = 2;
    const PRIORITY_LOW = 1;
    const PRIORITY_LOWEST = 0;

    protected string $name;
    protected string $namespace;
    protected string $directory;
    protected string $configId;
    protected int $bootPriority = self::PRIORITY_NORMAL;

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

    /**
     *
     */
    public function stop()
    {

    }

    /**
     * @return int
     */
    public function getBootPriority(): int
    {
        return $this->bootPriority;
    }

    /**
     * @param int $bootPriority
     */
    public function setBootPriority(int $bootPriority): void
    {
        $this->bootPriority = $bootPriority;
    }
}
<?php


namespace EvoSC\Classes;


use EvoSC\Exceptions\UnauthorizedException;
use EvoSC\Models\Player;
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

    /**
     * @param Player $player
     * @param string $accessRight
     * @throws UnauthorizedException
     */
    final static function authorize(Player $player, string $accessRight)
    {
        if (!$player->hasAccess($accessRight)) {
            list($childClass, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            throw new UnauthorizedException("$player is not authorized to call " . $caller['class'] . $caller['type'] . $caller['function']);
        }
    }
}
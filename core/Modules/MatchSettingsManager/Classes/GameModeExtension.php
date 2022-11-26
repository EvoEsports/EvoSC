<?php

namespace EvoSC\Modules\MatchSettingsManager\Classes;

use Illuminate\Support\Collection;

class GameModeExtension
{
    /**
     * @var string
     */
    private string $name;

    /**
     * @var Collection<int, ModeScriptSetting>
     */
    private Collection $settings;

    /**
     * @var string|null
     */
    private ?string $extendsMode;

    /**
     * @param string $name
     * @param Collection $settings
     * @param string|null $extendsMode
     * @throws \Exception
     */
    public function __construct(string $name, Collection $settings, string $extendsMode = null)
    {
        if (basename($name) == basename($extendsMode)) {
            throw new \Exception("Game modes may not extend themselves.");
        }

        $this->name = $name;
        $this->settings = $settings;
        $this->extendsMode = $extendsMode;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return basename($this->name);
    }

    /**
     * @return string
     */
    public function getFullName(): string
    {
        return $this->name;
    }

    /**
     * @return Collection
     */
    public function getSettings(): Collection
    {
        return $this->settings;
    }

    /**
     * @return string|null
     */
    public function getExtendsMode(): ?string
    {
        return $this->extendsMode;
    }
}
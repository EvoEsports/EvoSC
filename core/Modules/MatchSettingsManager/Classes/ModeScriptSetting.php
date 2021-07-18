<?php


namespace EvoSC\Modules\MatchSettingsManager\Classes;


use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class ModeScriptSetting implements Jsonable, Arrayable
{
    protected string $setting;
    protected string $description;
    protected string $default;
    protected string $value = '';
    protected string $type;

    /**
     * ModeScriptSetting constructor.
     * @param string $setting
     * @param string $type
     * @param string $description
     * @param string $default
     */
    public function __construct(string $setting, string $type, string $description, string $default)
    {
        $this->setting = $setting;
        $this->description = $description;
        $this->default = $default;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getSetting(): string
    {
        return $this->setting;
    }

    /**
     * @param string $setting
     */
    public function setSetting(string $setting): void
    {
        $this->setting = $setting;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getDefault(): string
    {
        return $this->default;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * @return bool
     */
    public function isDefaultValue(): bool
    {
        return $this->value == $this->default;
    }

    /**
     * @param int $options
     * @return string|void
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray());
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'setting'     => $this->setting,
            'description' => $this->description,
            'def'         => $this->default,
            'value'       => $this->value,
            'type'        => $this->type,
        ];
    }
}
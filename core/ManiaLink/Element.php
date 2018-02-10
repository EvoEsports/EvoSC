<?php

namespace esc\ManiaLink;


class Element
{
    private $style;

    public function __construct()
    {
        $this->style = new ManiaStyle();
    }

    public function setStyle(array $style = null)
    {
        if ($style) {
            $this->style->setBatch($style);
        }
    }

    public function getStyle(): ?ManiaStyle
    {
        return $this->style;
    }

    public function getWidth(): ?float
    {
        if ($this->style) {
            return $this->style->getWidth();
        }

        return null;
    }
}
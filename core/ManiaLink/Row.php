<?php

namespace esc\ManiaLink;


class Row
{
    private $padding;
    private $element;
    private $width;
    private $background;

    public function __construct(float $width, float $padding = 0.1)
    {
        $this->padding = $padding;
        $this->width = $width;
    }

    public function getPadding()
    {
        return $this->padding;
    }

    public function getHeight()
    {
        return $this->getElement()->getHeight() + (2 * $this->padding);
    }

    public function getElement(): Element
    {
        return $this->element;
    }

    public function setElement(Element $element)
    {
        $this->element = $element;
    }

    public function toString($offset)
    {
        $x = 0;
        $y = -$offset;

        $xml = '<quad pos="' . $x . ' ' . $y . '" z-index="-1" size="' . $this->width . ' ' . ($this->getElement()->getHeight() + 2 * $this->padding) . '" bgcolor="' . $this->background . '"/>';
        $xml .= $this->getElement()->toString($this->padding, $offset + $this->padding, $this->padding);

        return $xml;
    }

    public function setBackground(string $background)
    {
        $this->background = $background;
    }
}
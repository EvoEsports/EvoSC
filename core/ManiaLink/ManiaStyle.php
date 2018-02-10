<?php

namespace esc\ManiaLink;


class ManiaStyle
{
    private $width = 0;
    private $height;
    private $padding = 0;
    private $background = '0000';
    private $textcolor = 'FFFF';
    private $textsize = 1.0;
    private $paddingLeft = 1.0;
    private $valign = 'top';
    private $halign = 'left';

    public function setBatch(array $styling)
    {
        foreach ($styling as $key => $value) {
            switch ($key) {
                case 'width':
                    $this->width = (float)$value;
                    break;
                case 'height':
                    $this->height = (float)$value;
                    break;
                case 'padding':
                    $this->padding = (float)$value;
                    break;
                case 'bgcolor':
                    $this->background = $value;
                    break;
                case 'textcolor':
                    $this->textcolor = $value;
                    break;
                case 'textsize':
                    $this->textsize = (float)$value;
                    break;
                case 'valign':
                    $this->valign = $value;
                    break;
                case 'halign':
                    $this->halign = $value;
                    break;
                case 'padding-left':
                    $this->paddingLeft = (float)$value;
                    break;
            }
        }
    }

    public function getWidth(): float
    {
        return $this->width + (2 * $this->padding);
    }

    public function getBackground(): ?string
    {
        return $this->background;
    }

    public function getPadding(): ?float
    {
        return $this->padding;
    }

    public function getTextSize(): float
    {
        return $this->textsize;
    }

    public function getValign(): string
    {
        return $this->valign;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function getHalign(): string
    {
        return $this->halign;
    }

    public function getPaddingLeft(): float
    {
        return $this->paddingLeft;
    }

    public function getTextcolor(): string
    {
        return $this->textcolor;
    }
}
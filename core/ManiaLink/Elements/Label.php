<?php

namespace esc\ManiaLink\Elements;

use esc\ManiaLink\Element;

class Label extends Element
{
    private $text;

    public function __construct(string $text, array $style = null)
    {
        parent::__construct();

        $this->text = $text;
        $this->setStyle($style);
    }

    public function setText(string $text)
    {
        $this->text = $text;
    }

    public function toString(float $xOffset, int $layer)
    {
        $xml = '<label z-index="%d" pos="%.2f 0" valign="%s" halign="%s" textsize="%.2f" text="%s" textcolor="%s"/>';

        $textsize = $this->getStyle()->getTextSize();
        $valign = $this->getStyle()->getValign();
        $halign = $this->getStyle()->getHalign();
        $width = $this->getStyle()->getWidth();
        $paddingLeft = $this->getStyle()->getPaddingLeft();
        $textcolor = $this->getStyle()->getTextcolor();

        if($halign == 'right'){
            $xOffset += $width;
        }

        if($paddingLeft){
            $xOffset += $paddingLeft;
        }

        return sprintf($xml, $layer, $xOffset, $valign, $halign, $textsize, $this->text, $textcolor);
    }

    public function getHeight()
    {
        return $this->getStyle()->getHeight() ?: $this->getStyle()->getTextSize() * 1.9;
    }
}
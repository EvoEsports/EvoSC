<?php

namespace esc\ManiaLink;


use Illuminate\Database\Eloquent\Collection;

class Row
{
    private $elements;

    public function __construct()
    {
        $this->elements = new Collection();
    }

    public function addElement(Element ...$element)
    {
        foreach ($element as $e) {
            $this->elements->add($e);
        }
    }

    public function getHeight()
    {
        $max = 0;

        foreach ($this->elements as $element) {
            $height = $element->getHeight();

            if ($height > $max) {
                $max = $height;
            }
        }

        return $max;
    }

    public function toString(float $offsetLeft = 0.0, float $offsetTop = 0.0, int $layer)
    {
        $xml = '<frame z-index="%d" pos="%.2f %.2f">%s</frame>';

        $out = '';
        $xOffset = 0;

        foreach ($this->elements as $element) {
            $out .= $element->toString($xOffset, $layer + 1);
            $xOffset += $element->getWidth();
        }

        return sprintf($xml, $layer, $offsetLeft, -$offsetTop, $out);
    }
}
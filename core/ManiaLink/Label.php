<?php

namespace esc\ManiaLink;


class Label extends Element
{
    const ALIGN_LEFT = 1;
    const ALIGN_CENTER = 2;
    const ALIGNT_RIGHT = 3;

    private $text;
    private $textSize;
    private $alignment;

    private function __construct(string $text, float $textSize = 1.0, int $alignment = 1)
    {
        $this->text = $text;
        $this->textSize = $textSize;
        $this->alignment = $alignment;
    }

    public static function create(string $text, float $textSize = 1.0, int $alignment = 1): Label
    {
        return new Label($text, $textSize);
    }

    public function toString($offsetX, $offsetY, $padding): string
    {
        $height = $this->getHeight();
        return '<label pos="' . $offsetX . ' ' . -$offsetY . '" z-index="' . 0 . '" size="' . 50 . ' ' . $height . '" scale="' . $this->textSize . '" text="' . $this->text . '" halign="' . $this->alignment . '" />';
    }

    public function getHeight(): float
    {
        return $this->textSize * 3.75;
    }
}
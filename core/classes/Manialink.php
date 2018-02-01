<?php

namespace esc\classes;


use esc\controllers\RpcController;
use Illuminate\Database\Eloquent\Collection;

class Manialink
{
    private $x;
    private $y;
    private $scale;
    private $name;
    private $elements;

    public function __construct(int $x, int $y, string $name, float $scale = 1.0)
    {
        $this->name = $name;
        $this->x = $x;
        $this->y = $y;
        $this->scale = $scale;
        $this->elements = new Collection();
    }

    public function addQuad(float $x, float $y, float $width, float $height, string $bgColor = '0003', int $zIndex = 0)
    {
        $xml = '<quad pos="' . $x . ' ' . $y . '" z-index="' . $zIndex . '" size="' . $width . ' ' . $height . '" bgcolor="' . $bgColor . '"/>';
        $this->elements->add($xml);
    }

    public function addLabel(float $x, float $y, float $width, float $height, string $text, float $scale = 1, int $zIndex = 0, string $align = 'left')
    {
        $xml = '<label pos="' . $x . ' ' . $y . '" z-index="' . $zIndex . '" size="' . $width . ' ' . $height . '" scale="' . $scale . '" text="' . $text . '" halign="' . $align . '" />';
        $this->elements->add($xml);
    }

    private function toString()
    {
        $ml = '<manialink id="' . $this->name . '" version="3">
        <frame pos="' . $this->x . ' ' . $this->y . '" scale="' . $this->scale . '">
        %s
        </frame>
        </manialink>';

        $inner = '';
        foreach ($this->elements as $element) {
            $inner .= "\n            $element";
        }

        $inner .= "\n        ";

        return sprintf($ml, $inner);
    }

    public function sendToAll()
    {
        RpcController::call('SendDisplayManialinkPage', [$this->toString(), 0, false]);
    }
}
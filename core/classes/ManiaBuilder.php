<?php

namespace esc\classes;


use esc\controllers\RpcController;
use esc\ManiaLink\Row;
use esc\models\Player;
use Illuminate\Database\Eloquent\Collection;

class ManiaBuilder
{
    const STICK_LEFT = 1;
    const STICK_RIGHT = 2;
    const STICK_TOP = 3;
    const STICK_BOTTOM = 4;

    private $id;
    private $x;
    private $y;
    private $width;
    private $height;
    private $scale;

    private $rows;

    public function __construct(string $id, int $x = 0, int $y = 0, int $width, int $height, float $scale = 1.0)
    {
        $this->id = $id;
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
        $this->scale = $scale;
        $this->rows = new Collection();
    }

    public function addRow(Row $row)
    {
        $this->rows->add($row);
    }

    public function sendToPlayer(Player $player)
    {
        Log::info("Sending manialink to " . $player->nick(true));
        RpcController::call('SendDisplayManialinkPageToLogin', [$player->login, $this->toString(), 0, false]);
    }

    public function sendToAll()
    {
        RpcController::call('SendDisplayManialinkPage', [$this->toString(), 0, false]);
    }

    public function toString()
    {
        $ml = '<manialink id="' . $this->id . '" version="3">
        <frame pos="' . $this->x . ' ' . $this->y . '" scale="' . $this->scale . '">
        %s
        </frame>
        </manialink>';

        $offset = 0;
        $inner = '';
        foreach ($this->rows as $row) {
            $inner .= $row->toString($offset);
            $offset += $row->getHeight();
        }

        return sprintf($ml, $inner);
    }
}
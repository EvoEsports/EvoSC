<?php

namespace esc\classes;


use esc\controllers\RpcController;
use esc\ManiaLink\Row;
use esc\models\Player;
use Illuminate\Database\Eloquent\Collection;

class ManiaBuilder
{
    const STICK_LEFT = 1001;
    const STICK_RIGHT = 1002;
    const STICK_TOP = 1003;
    const STICK_BOTTOM = 1004;

    private $id;
    private $x;
    private $y;
    private $width;
    private $height;
    private $scale;

    private $rows;

    private $lastManiaLink;
    private static $manialinkCache;

    public function __construct(string $id, int $x = 0, int $y = 0, int $width, int $height, float $scale = 1.0)
    {
        $this->id = $id;
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
        $this->scale = $scale;
        $this->rows = new Collection();

        ManiaBuilder::createCache();

        if ($x == ManiaBuilder::STICK_LEFT) {
            $this->x = -160;
        }
        if ($x == ManiaBuilder::STICK_RIGHT) {
            $this->x = 160 - $width;
        }
        if ($y == ManiaBuilder::STICK_TOP) {
            $this->y = 90;
        }
        if ($y == ManiaBuilder::STICK_BOTTOM) {
            $this->y = -90;
        }
    }

    public static function createCache(){
        if(self::$manialinkCache == null){
            self::$manialinkCache = new Collection();
        }
    }

    public static function cacheSave(ManiaBuilder $builder)
    {
        self::$manialinkCache->add($builder);
    }

    public static function cacheLoad(string $id): ?ManiaBuilder
    {
        $out = self::$manialinkCache->first();
        return $out;
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
        $hash = md5(serialize($this));
        if ($this->lastManiaLink != $hash) {
            RpcController::call('SendDisplayManialinkPage', [$this->toString(), 0, false]);
            $this->lastManiaLink = $hash;
        } else {
            Log::info("Manialink identical, not sending.");
        }
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
            $inner .= $row->toString($this->width, $offset);
            $offset += $row->getHeight();
        }

        return sprintf($ml, $inner);
    }
}
<?php

namespace esc\classes;


use esc\controllers\RpcController;
use esc\ManiaLink\Element;
use esc\ManiaLink\ManiaStyle;
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
    private $style;

    private $lastManiaLink;
    private static $manialinkCache;

    public function __construct(string $id, int $x = 0, int $y = 0, int $width, int $height, float $scale = 1.0, array $style = null)
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

        $this->style = new ManiaStyle();
        if ($style) {
            $this->style->setBatch($style);
        }
    }

    public static function createCache()
    {
        if (self::$manialinkCache == null) {
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

    public function addRow(Element ...$elements)
    {
        $row = new Row();

        foreach($elements as $e){
            $row->addElement($e);
        }

        $this->rows->add($row);
    }

    public function sendToPlayer(Player $player)
    {
        Log::info("Sending manialink to " . $player->nick(true));
        RpcController::call('SendDisplayManialinkPageToLogin', [$player->login, $this->toString(), 0, false]);
    }

    public function sendToAll()
    {
//        RpcController::call('SendDisplayManialinkPage', [$this->toString(), 0, false]);
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
        $xml = '<manialink id="' . $this->id . '" version="3"><frame pos="%.2f %.2f" scale="%.2f"><quad pos="0 0" z-index="%d" size="%.2f %.2f" bgcolor="%s"/>%s</frame></manialink>';

        $layer = 0;
        $padding = $this->style->getPadding();
        $background = $this->style->getBackground();

        $inner = '';
        $offsetTop = $padding;
        foreach ($this->rows as $row) {
            $inner .= $row->toString($padding, $offsetTop, $layer + 1);
            $offsetTop += $row->getHeight();
        }

        $manialink = sprintf($xml, $this->x, $this->y, $this->scale, $layer, $this->width, $this->height, $background, $inner);

//        echo str_replace('><', ">\n<", $manialink) . "\n";

        return $manialink;
    }
}
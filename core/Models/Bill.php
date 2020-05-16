<?php

namespace EvoSC\Models;


class Bill
{
    public Player $player;
    public $id;
    public $created_at;
    public $label;
    public $successFunction;
    public $failFunction;
    public $amount;
    public bool $expired;

    public function __construct(Player $player, $id, $amount, $created_at, $label, array $successFunction = null, array $failFunction = null)
    {
        $this->player          = $player;
        $this->id              = $id;
        $this->created_at      = $created_at;
        $this->label           = $label;
        $this->successFunction = $successFunction;
        $this->failFunction    = $failFunction;
        $this->amount          = $amount;
        $this->expired         = false;
    }
}
<?php

namespace esc\Models;


class Bill
{
    public $player;
    public $id;
    public $created_at;
    public $label;
    public $successFunction;
    public $failFunction;
    public $amount;
    public $expired;

    public function __construct(Player $player, $id, $amount, $created_at, $label, array $successFunction, array $failFunction)
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
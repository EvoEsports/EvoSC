<?php

namespace esc\Controllers;


use Carbon\Carbon;
use esc\Classes\Log;
use esc\Classes\Server;
use esc\Classes\Timer;
use esc\Models\Bill;
use esc\Models\Player;

class PlanetsController
{
    private static $openBills;
    private static $billStates;

    public static function init()
    {
        self::$openBills  = collect();
        self::$billStates = explode(', ', 'CreatingTransaction, Issued, ValidatingPayement, Payed, Refused, Error');

        Timer::create('bills.check', 'PlanetsController::checkBills', '1s');
    }

    public static function checkBill(Bill &$bill)
    {
        $billState = Server::getBillState($bill->id);

        switch ($billState->state) {
            case 4:
                call_func($bill->successFunction, $bill->player, $bill->amount);
                $bill->expired = true;
                break;

            case 5:
                if($bill->failFunction){
                    call_func($bill->failFunction, $bill->player);
                }
                $bill->expired = true;
                break;

            case 6:
                Log::logAddLine('PlanetController', $billState->stateName);
                $bill->expired = true;
                break;
        }
    }

    public static function checkBills()
    {
        $bills = self::$openBills->where('expired', false);
        $bills->each([self::class, 'checkBill']);
        Timer::create('bills.check', 'PlanetsController::checkBills', '1s');
    }

    public static function createBill(Player $player, int $amount, string $label, string $successFunction, string $failFunction = null)
    {
        $billId = Server::sendBill($player->Login, $amount, $label);

        if (!$billId || !is_int($billId)) {
            Log::logAddLine('PlanetsController', 'Failed to create bill');
            return;
        }

        $bill = new Bill($player, $billId, $amount, Carbon::now(), $label, $successFunction, $failFunction);
        self::$openBills->push($bill);
    }
}
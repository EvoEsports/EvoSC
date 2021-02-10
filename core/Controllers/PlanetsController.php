<?php

namespace EvoSC\Controllers;


use Carbon\Carbon;
use EvoSC\Classes\Log;
use EvoSC\Classes\Server;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\Bill;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;

/**
 * Class PlanetsController
 *
 * Create planets payments.
 *
 * @package EvoSC\Controllers
 */
class PlanetsController implements ControllerInterface
{
    /**
     * @var Collection
     */
    private static Collection $openBills;
    /**
     * @var string[]
     */
    private static array $billStates = [
        'CreatingTransaction',
        'Issued',
        'ValidatingPayement',
        'Payed',
        'Refused',
        'Error'
    ];

    /**
     *
     */
    public static function init()
    {
        self::$openBills = collect();
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        if (isTrackmania()) {
            return;
        }

        Timer::create('bills.check', [self::class, 'checkBills'], '1s');
    }

    /**
     * Check payment state.
     *
     * @param Bill $bill
     */
    public static function checkBill(Bill &$bill)
    {
        $billState = Server::getBillState($bill->id);

        switch ($billState->state) {
            case 4:
                call_user_func($bill->successFunction, $bill->player, $bill->amount);
                $bill->expired = true;
                break;

            case 5:
                if ($bill->failFunction) {
                    call_user_func($bill->failFunction, $bill->player);
                }
                $bill->expired = true;
                break;

            case 6:
                Log::write($billState->stateName);
                $bill->expired = true;
                break;
        }
    }

    /**
     * Check all payment states.
     */
    public static function checkBills()
    {
        $bills = self::$openBills->where('expired', false);
        $bills->each([self::class, 'checkBill']);
        Timer::create('bills.check', [PlanetsController::class, 'checkBills'], '1s');
    }

    /**
     * Create payment request.
     *
     * @param Player $player
     * @param int $amount
     * @param string $label
     * @param array $successFunction
     * @param array|null $failFunction
     */
    public static function createBill(
        Player $player,
        int $amount,
        string $label,
        array $successFunction,
        array $failFunction = null
    )
    {
        $billId = Server::sendBill($player->Login, $amount, $label);

        if (!$billId || !is_int($billId)) {
            Log::write('Failed to create bill');

            return;
        }

        $bill = new Bill($player, $billId, $amount, Carbon::now(), $label, $successFunction, $failFunction);
        self::$openBills->push($bill);
    }
}
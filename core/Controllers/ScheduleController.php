<?php

namespace EvoSC\Controllers;

use EvoSC\Classes\Controller;
use EvoSC\Classes\DB;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\Schedule;
use Exception;

class ScheduleController extends Controller implements ControllerInterface
{
    public static function init()
    {
    }

    public static function start(string $mode, bool $isBoot)
    {
        Timer::create('check_schedule', [self::class, 'checkSchedule'], '30s', true);
    }

    public static function checkSchedule()
    {
        $tasks = Schedule::where('execute_at', '<', now()->toDateTimeString())
            ->whereNull('failed')
            ->get();

        foreach ($tasks as $task) {
            try {
                $arguments = unserialize($task->arguments);
                $callback = ManiaLinkEvent::getCallback(serverPlayer(), $task->event);

                if ($callback) {
                    call_user_func_array($callback, $arguments);
                    $task->delete();
                }
            } catch (Exception $e) {
                Log::errorWithCause('Failed to execute scheduled task', $e);

                $task->update(['failed' => 1, 'stack_trace' => $e->getMessage() . "\n" . $e->getTraceAsString()]);
            }
        }
    }
}

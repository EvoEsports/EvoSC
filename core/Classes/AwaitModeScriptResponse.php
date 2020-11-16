<?php


namespace EvoSC\Classes;


use Illuminate\Support\Collection;

class AwaitModeScriptResponse
{
    private static Collection $waiters;

    /**
     * @param string $responseId
     * @param callable $action
     * @param mixed ...$arguments
     */
    public static function add(string $responseId, callable $action, ...$arguments)
    {
        if (!isset(self::$waiters)) {
            self::$waiters = collect();
        }

        self::$waiters->put($responseId, (object)[
            'action' => $action,
            'arguments' => $arguments
        ]);
    }

    /**
     * @param string $responseId
     * @return bool
     */
    public function execute(string $responseId): bool
    {
        if (!isset(self::$waiters)) {
            return false;
        }

        $waiter = self::$waiters->get($responseId);

        if($waiter){
            $callback = $waiter->action;
            $callback(...$waiter->arguments);
            self::$waiters->forget($responseId);

            return false;
        }

        return true;
    }
}
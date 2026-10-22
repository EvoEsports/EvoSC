<?php

namespace EvoSC\Commands;

use Symfony\Component\Console\Command\SignalableCommandInterface;

class EscRunSignal extends EscRun implements SignalableCommandInterface {
    /**
     * Returns the signals which EvoSC subscribes to
     *
     * @return array
     */
    public function getSubscribedSignals(): array
    {
        // return here any of the constants defined by PCNTL extension
        // https://www.php.net/manual/en/pcntl.constants.php
        return [SIGTERM, SIGINT];
    }

    /**
     * Signal handler
     *
     * @param int $signal
     * @param int|false $previousExitCode
     * @return int|false
     */
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        if ($signal == SIGTERM || $signal == SIGINT) {
            $this->shutdownEvoSC();
            return 0;
        }

        return false;
    }
}
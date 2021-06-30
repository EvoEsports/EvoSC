<?php

namespace EvoSC\Commands;

use EvoSC\Commands\EscRun;
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
        return [SIGTERM];
    }

    /**
     * Signal handler
     *
     * @param int $signal
     */
    public function handleSignal(int $signal): void
    {
        if ($signal == SIGTERM) {
            $this->shutdownEvoSC();
        }
    }
}
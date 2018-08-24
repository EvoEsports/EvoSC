<?php

namespace esc\Exceptions;


use esc\Classes\Log;

class InvalidCallbackException extends \Exception
{
    /**
     * InvalidCallbackException constructor.
     * @param string|null $msg
     */
    public function __construct(string $msg = null)
    {
        Log::logAddLine('Exception', $msg, false);
    }
}
<?php


namespace EvoSC\Exceptions;


use Throwable;

class FilePathNotAbsoluteException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
<?php

namespace esc\Classes;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Log
 *
 * Logging and colored cli-output.
 *
 * @package esc\Classes
 */
class Log
{
    /**
     * @var OutputInterface
     */
    private static OutputInterface $output;

    /**
     * Get the output-interface
     *
     * @return OutputInterface|null
     */
    public static function getOutput(): ?OutputInterface
    {
        return self::$output;
    }

    /**
     * Write a line to the cli.
     *
     * @param string $line
     */
    private static function writeLn(string $line)
    {
        $output = self::getOutput();

        if ($output) {
            self::getOutput()->writeln(stripAll($line));
        }
    }

    /**
     * Add a log entry and output it to cli as default. You can use:
     * - isVerbose() (-v)
     * - isVeryVerbose() (-vv)
     * - isDebug() (-vvv)
     * to manage output to cli (all lines will be added to the log-file, independent from verbosity-level).
     *
     * @param string $string
     * @param bool $echo
     * @param null $caller
     */
    public static function write(string $string, $echo = true, $caller = null)
    {

        if (!$caller) {
            list($childClass, $caller) = debug_backtrace(false, 2);
        }

        if (isset($caller['class']) && isset($caller['type'])) {
            $callerClassName = isVerbose() ? $caller['class'] : preg_replace('#^.+[\\\]#', '', $caller['class']);
            $callingClass = $callerClassName . $caller['type'] . $caller['function'];
        } else {
            $callingClass = $caller['function'];
        }

        if (count($caller['args']) > 0) {
            $callingClass .= '(...)';
        } else {
            $callingClass .= '';
        }

        if (isDebug()) {
            $callingClass .= "\nData: " . json_encode($caller['args']);
        }

        $date = date("Y-m-d", time());
        $time = date("H:i:s", time());
        $logFile = logDir($date . '.txt');

        $line = sprintf("[%s] %s: %s", $time, $callingClass, $string);

        $line = stripAll($line);

        if ($echo == true || isDebug()) {
            self::writeLn($line);
        }

        File::appendLine($logFile, $line);
    }

    /**
     * Log a info-message.
     *
     * @param      $message
     * @param bool $echo
     */
    public static function info($message, bool $echo = true)
    {

        list($childClass, $caller) = debug_backtrace(false, 2);
        self::write('<info>' . $message . '</>', $echo, $caller);
    }

    /**
     * Log a error-message.
     *
     * @param      $message
     * @param bool $echo
     */
    public static function error($message, bool $echo = true)
    {

        list($childClass, $caller) = debug_backtrace(false, 2);
        self::write('<error>' . $message . '</>', $echo, $caller);
    }

    /**
     * Log a warning-message
     *
     * @param      $message
     * @param bool $echo
     */
    public static function warning($message, bool $echo = true)
    {

        list($childClass, $caller) = debug_backtrace(false, 2);
        self::write('<fg=red>' . $message . '</>', $echo, $caller);
    }

    /**
     * @param string $message
     * @param bool $echo
     */
    public static function cyan(string $message, bool $echo = true)
    {
        list($childClass, $caller) = debug_backtrace(false, 2);
        self::write("<fg=cyan;options=bold>$message</>", $echo, $caller);
    }

    /**
     * Do not use (internal function). Set the output interface.
     *
     * @param OutputInterface $output
     */
    public static function setOutput(OutputInterface $output)
    {
        self::$output = $output;
    }
}
<?php

namespace EvoSC\Classes;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Log
 *
 * Logging and colored cli-output.
 *
 * @package EvoSC\Classes
 */
class Log
{
    /**
     * @var OutputInterface
     */
    private static OutputInterface $output;

    private static bool $singleFileMode;
    private static string $logPrefix;

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
            list($childClass, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        }

        if (isset($caller['class']) && isset($caller['type'])) {
            $callerClassName = isVerbose() ? $caller['class'] : preg_replace('#^.+[\\\]#', '', $caller['class']);
            $callingClass = $callerClassName . $caller['type'] . $caller['function'];
        } else {
            $callingClass = $caller['function'];
        }

        if (isDebug()) {
            $callingClass .= "\nData: " . json_encode($caller);
        }

        $time = date("H:i:s", time());
        $line = sprintf("[%s] %s(): %s", $time, $callingClass, $string);

        if (!isset(self::$singleFileMode)) {
            echo $line . "\n";
            return;
        }

        if (self::$singleFileMode) {
            $logFile = logDir(self::$logPrefix . '.txt');
        } else {
            $date = date("Y-m-d", time());
            $logFile = logDir(self::$logPrefix . '_' . $date . '.txt');
        }

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
        list($childClass, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        self::write('<info>' . $message . '</>', $echo, $caller);
    }

    /**
     * Log a error-message.
     *
     * @param      $message
     * @param bool $echo
     */
    public static function error($message, bool $echo = true, int $limit = 2)
    {
        list($childClass, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
        self::write('<error>' . $message . '</>', $echo, $caller);
    }

    /**
     * Log an error message and cause stacktrace.
     *
     * @param      $message
     * @param      $throwable
     * @param bool $echo
     */
    public static function errorWithCause($message, \Throwable $throwable, bool $echo = true)
    {
        self::error($message . ': ' . $throwable->getMessage(), $echo, 3);

        if (isVerbose())
        {
            self::write($throwable->getTraceAsString(), $echo);
        }
    }

    /**
     * Log a warning-message
     *
     * @param      $message
     * @param bool $echo
     */
    public static function warning($message, bool $echo = true, $limit = 2)
    {
        list($childClass, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
        self::write('<warning>' . $message . '</>', $echo, $caller);
    }

    /**
     * Log a warning message and cause stacktrace.
     *
     * @param      $message
     * @param      $throwable
     * @param bool $echo
     */
    public static function warningWithCause($message, \Throwable $throwable, bool $echo = true)
    {
        self::warning($message . ': ' . $throwable->getMessage(), $echo, 3);

        if (isVeryVerbose())
        {
            self::write($throwable->getTraceAsString(), $echo);
        }
    }

    /**
     * @param string $message
     * @param bool $echo
     */
    public static function cyan(string $message, bool $echo = true)
    {
        list($childClass, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        self::write("<fg=cyan;options=bold>$message</>", $echo, $caller);
    }

    /**
     * Do not use (internal function). Set the output interface.
     *
     * @param OutputInterface $output
     */
    public static function setOutput(OutputInterface $output)
    {
        self::$singleFileMode = (bool)config('server.logs.single-file', false);
        self::$logPrefix = (string)config('server.logs.prefix', 'evosc');

        $warningOutputFormatterStyle = new OutputFormatterStyle('red');
        $output->getFormatter()->setStyle('warning', $warningOutputFormatterStyle);

        self::$output = $output;
    }
}

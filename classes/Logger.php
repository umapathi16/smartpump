<?php

class Logger
{
    public static function info($message, $file = 'gateway.log')
    {
        self::write('INFO', $message, $file);
    }

    public static function error($message, $file = 'error.log')
    {
        self::write('ERROR', $message, $file);
    }

    private static function write($level, $message, $file)
    {
        $logPath =
            __DIR__ . '/../logs/' . $file;

        $time =
            date('Y-m-d H:i:s');

        $formatted =
            "[{$time}] [{$level}] {$message}" . PHP_EOL;

        file_put_contents(
            $logPath,
            $formatted,
            FILE_APPEND
        );
    }
}

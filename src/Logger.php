<?php

namespace DatabaseDonor;

class Logger
{

    protected function __construct() { }

    protected function __clone() { }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize.");
    }

    public static function log($msg): void
    {
        $filename = "logs/" . date("Y-m-d");
        $file = fopen($filename, "a");
        fwrite($file, date("G:i:s") . ' | ' . $msg . PHP_EOL);
        fclose($file);
    }

    public static function logSQLstm($stm): void
    {
        $filename = "logs/" . "sql_" .date("Y-m-d h:i:s");
        $file = fopen($filename, "a");
        fwrite($file, date("G:i:s") . ' | ' . $stm . PHP_EOL);
        fclose($file);
    }
}
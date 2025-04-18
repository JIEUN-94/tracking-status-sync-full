<?php
namespace App\Core;

class Logger {
    public static function log(string $message): void {
        $filename = __DIR__ . '/../../logs/log_' . date('Ymd') . '.txt';
        $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        file_put_contents($filename, $entry, FILE_APPEND);
    }
}

<?php
namespace App\Core;

class Config {
    public static function get(string $key): ?string {
        static $env = null;
        if (!$env) {
            $env = require __DIR__ . '/../../config/.env.php';
        }
        return $env[$key] ?? null;
    }
}

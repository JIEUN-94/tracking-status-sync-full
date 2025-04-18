<?php
namespace App\Core;

use App\Interfaces\CourierTrackerInterface;
use App\Core\Logger;

class CourierDispatcher {
    protected array $map = [];

    public function __construct() {
        foreach (glob(__DIR__ . '/../Couriers/*Tracker.php') as $file) {
            require_once $file;
            $className = 'App\\Couriers\\' . basename($file, '.php');
            $key = strtolower(str_replace('Tracker.php', '', basename($file)));
            $this->map[$key] = new $className();
        }
    }

    public function dispatch(string $courierCode, string $hawb): array {
        $courierCode = strtolower($courierCode);
        $courier = $this->map[$courierCode] ?? null;

        if (!$courier || !($courier instanceof CourierTrackerInterface)) {
            Logger::log("❌ Unknown courier requested: {$courierCode}");
            return ['error' => "❌ Unknown courier: {$courierCode}"];
        }

        try {
            $result = $courier->track($hawb);
            Logger::log("✅ [{$courierCode}] HAWB: {$hawb} → Status: {$result['status']}");
            return $result;
        } catch (\Throwable $e) {
            Logger::log("❌ Exception: " . $e->getMessage());
            return ['error' => 'Tracking failed', 'detail' => $e->getMessage()];
        }
    }
}

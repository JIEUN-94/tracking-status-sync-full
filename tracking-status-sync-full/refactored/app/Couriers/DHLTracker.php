<?php
namespace App\Couriers;

use App\Interfaces\CourierTrackerInterface;
use App\Core\Config;

class DHLTracker implements CourierTrackerInterface {
    public function track(string $hawb): array {
        $apiKey = Config::get('DHL_API_KEY');
        return [
            'hawb' => $hawb,
            'status' => '배송중',
            'courier' => 'DHL',
            'last_update' => date('Y-m-d H:i:s'),
            'api_key_used' => $apiKey
        ];
    }
}

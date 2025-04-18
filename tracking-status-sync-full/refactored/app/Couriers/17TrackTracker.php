<?php
namespace App\Couriers;

use App\Interfaces\CourierTrackerInterface;
use App\Core\Config;

class 17TrackTracker implements CourierTrackerInterface {
    public function track(string $hawb): array {
        $apiKey = Config::get('17Track_API_KEY');
        return [
            'hawb' => $hawb,
            'status' => '배송중',
            'courier' => '17Track',
            'last_update' => date('Y-m-d H:i:s'),
            'api_key_used' => $apiKey
        ];
    }
}
